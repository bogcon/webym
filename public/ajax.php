<?php
/**
 * Ajax requests are done here.
 * 
 * @author      Bogdan Constantinescu <bog_con@yahoo.com>
 * @link        GitHub  https://github.com/bogcon/webym
 * @license     New BSD License (http://opensource.org/licenses/BSD-3-Clause); see LICENSE.txt
 */

session_start();
ob_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.local.php';

use \BogCon\YahooMessengerApi\Client;
use \BogCon\WebYm\Logger;

/**
 * Check login.
 * @return boolean  TRUE if user is logged in, FALSE otherwise.
 */
function checkLocalLogin()
{
    return isset($_SESSION['username']) && isset($_SESSION['pwd'])
        && isset($_SESSION['ym_tokens']) && isset($_SESSION['ym_session'])
        && isset($_SESSION['ym_notifications_seq']);
}

$arrReturnValue = array('status' => 'error', 'response' => 'Unknown request.');

if (isset($_POST['ajax_action'])) {
    switch ($_POST['ajax_action']) {
        /* LOGOUT */
        case 'logout':
            if (!checkLocalLogin()) { // return success if already logged out
                $arrReturnValue['status'] = 'success';
                $arrReturnValue['response'] = 'You have successfully logged out.';
            } else {
                try {
                    $objYM = new Client($_SESSION['username'], $_SESSION['pwd'], APP_CONSUMER_KEY, APP_CONSUMER_SECRET);
                    $objYM->setTokens($_SESSION['ym_tokens'])
                          ->setSession($_SESSION['ym_session'])
                          ->logOut();

                    $arrReturnValue['status'] = 'success';
                    $arrReturnValue['response'] = 'You have successfully logged out.';

                    unset($_SESSION['username']);
                    unset($_SESSION['pwd']);
                    unset($_SESSION['ym_tokens']);
                    unset($_SESSION['ym_session']);
                    unset($_SESSION['ym_notifications_seq']);
                } catch (\Exception $objEx) {
                    $arrReturnValue['response'] = 'Could not logout.';
                    Logger::log($objEx->getMessage());
                }
            }
            break;
        /* LOGIN */
        case 'login':
            if (!isset($_POST['username']) || !strlen(trim($_POST['username']))) {
                $arrReturnValue['response'] = 'Invalid param username.';
            } elseif (!isset($_POST['password']) || !strlen(trim($_POST['password']))) {
                $arrReturnValue['response'] = 'Invalid param password.';
            } else {
                $intLoginState = isset($_POST['invisible']) && $_POST['invisible'] == 1 ? Client::USER_IS_OFFLINE : Client::USER_IS_ONLINE;
                try {
                    $objYM = new Client(trim($_POST['username']), trim($_POST['password']), APP_CONSUMER_KEY, APP_CONSUMER_SECRET);
                    $objYM->logIn($intLoginState);

                    /* get groups & contacts */
                    $groups = $objYM->fetchGroups();
                    $arrAjaxResponse = array();
                    foreach ($groups['groups'] as $group) {
                        $groupName = htmlspecialchars($group['group']['name']);
                        $arrAjaxResponse[$groupName] = array();
                        foreach ($group['group']['contacts'] as $contact) {
                            $buddy = array(
                                'id' => $contact['contact']['id'],
                                'name' => (isset($contact['contact']['addressbook']['firstname']) ? $contact['contact']['addressbook']['firstname'] : '')
                                        . ' '
                                        . (isset($contact['contact']['addressbook']['lastname']) ? $contact['contact']['addressbook']['lastname'] : ''),
                            );
                            if (!strlen(trim($buddy['name']))) {
                                $buddy['name'] = $buddy['id'];
                            }
                            $buddy['name'] = htmlspecialchars(trim($buddy['name']));

                            switch ($contact['contact']['presence']['presenceState']) {
                                case Client::USER_IS_ONLINE:
                                    $buddy['state'] = 'online';
                                    $buddy['status'] = isset($contact['contact']['presence']['presenceMessage']) ? $contact['contact']['presence']['presenceMessage'] : '';
                                    break;
                                case Client::USER_IS_IDLE:
                                    $buddy['state'] = 'idle';
                                    $buddy['status'] = Client::$notificationsPresenceStatuses[Client::USER_IS_IDLE];
                                    break;
                                case Client::USER_IS_BUSY:
                                    $buddy['state'] = 'busy';
                                    $buddy['status'] = isset($contact['contact']['presence']['presenceMessage']) ? $contact['contact']['presence']['presenceMessage'] : Client::$notificationsPresenceStatuses[Client::USER_IS_BUSY];
                                    break;
                                default:
                                    $buddy['state'] = 'offline';
                                    $buddy['status'] = '';
                            }
                            $buddy['status'] = htmlspecialchars($buddy['status']);
                            $arrAjaxResponse[$groupName][] = $buddy;
                        }
                    }
                    
                    /* set remember-me cookie */
                    if (isset($_POST['remember_me']) && $_POST['remember_me'] == 1) {
                        setcookie('ym-remember-me', trim($_POST['username']), time() + 3600 * 24 * 30);
                    } else {
                        setcookie('ym-remember-me', false, time() - 3600);
                    }
                    
                    /* save local session */
                    $_SESSION['username'] = trim($_POST['username']);
                    $_SESSION['pwd'] = trim($_POST['password']);
                    $_SESSION['ym_tokens'] = $objYM->getTokens();
                    $_SESSION['ym_session'] = $objYM->getSession();
                    $_SESSION['ym_notifications_seq'] = 0;

                    $arrReturnValue['response'] = array(
                        'user_avatar' => $objYM->fetchCustomAvatar($_SESSION['ym_session']['primaryLoginId']),
                        'contacts' => $arrAjaxResponse,
                    );
                    $arrReturnValue['status'] = 'success';
                } catch (Exception $objEx) {
                    if (false !== strpos($objEx->getMessage(), 'Error=LoginDoesntExist')
                        || false !== strpos($objEx->getMessage(), 'Error=InvalidPassword')) {
                        $arrReturnValue['response'] = 'Invalid username or password.';
                    } else {
                        $arrReturnValue['response'] = 'Could not log in.';
                        Logger::log($objEx->getMessage());
                    }
                }
            }
            break;
        /* SEND MESSAGE */
        case 'send_message':
            if (!checkLocalLogin()) {
                $arrReturnValue['response'] = 'You must be logged in order to send messages.';
            } elseif (!isset($_POST['target_id']) || !strlen(trim($_POST['target_id']))) {
                $arrReturnValue['response'] = 'Invalid param target id.';
            } elseif (!isset($_POST['message']) || !strlen(trim($_POST['message']))) {
                $arrReturnValue['response'] = 'Invalid param message.';
            } else {
                $network = (isset($_POST['network']) && strlen(trim($_POST['network']))) ? trim($_POST['network']) : 'yahoo';
                try {
                    $objYM = new Client($_SESSION['username'], $_SESSION['pwd'], APP_CONSUMER_KEY, APP_CONSUMER_SECRET);
                    $objYM->setTokens($_SESSION['ym_tokens'])
                        ->setSession($_SESSION['ym_session'])
                        ->sendMessage(trim($_POST['message']), trim($_POST['target_id']), $network);

                    $arrReturnValue['status'] = 'success';
                    $arrReturnValue['response'] = 'Message was successfully sent.';
                    /* check if token was renewed during last call */
                    if ($objYM->isTokenRenewed()) {
                        $_SESSION['ym_tokens'] = $objYM->getTokens();
                    }
                } catch (Exception $objEx) {
                    $arrReturnValue['response'] = 'Could not send message.';
                    Logger::log($objEx->getMessage());
                }
            }
            break;
        /* CHANGE STATUS */
        case 'change_status':
            if (!checkLocalLogin()) {
                $arrReturnValue['response'] = 'You must be logged in order to change status.';
            } elseif (!isset($_POST['state']) || !strlen(trim($_POST['state']))) {
                $arrReturnValue['response'] = 'Invalid param state.';
            } elseif (!isset($_POST['state_message'])) {
                $arrReturnValue['response'] = 'Invalid param state message.';
            } else {
                switch ($_POST['state']) {
                    case 'idle':
                        $state = Client::USER_IS_IDLE;
                        break;
                    case 'busy':
                        $state = Client::USER_IS_BUSY;
                        break;
                    case 'invisible':
                        $state = Client::USER_IS_OFFLINE;
                        break;
                    default:
                        $state = Client::USER_IS_ONLINE;
                }
                try {
                    $objYM = new Client($_SESSION['username'], $_SESSION['pwd'], APP_CONSUMER_KEY, APP_CONSUMER_SECRET);
                    $objYM->setTokens($_SESSION['ym_tokens'])
                        ->setSession($_SESSION['ym_session'])
                        ->changePresenceState($state, trim($_POST['state_message']));

                    $arrReturnValue['status'] = 'success';
                    $arrReturnValue['response'] = 'Presence updated.';
                    /* check if token was renewed during last call */
                    if ($objYM->isTokenRenewed()) {
                        $_SESSION['ym_tokens'] = $objYM->getTokens();
                    }
                } catch (Exception $objEx) {
                    $arrReturnValue['response'] = 'Could not update presence.';
                    Logger::log($objEx->getMessage());
                }
            }
            break;
        default:
            $arrReturnValue['response'] = 'Unknown ajax action.';
    }
} elseif (isset($_GET['ajax_action'])) {
    switch ($_GET['ajax_action']) {
        /* GET AVATAR */
        case 'get_avatar':
            if (!checkLocalLogin()) {
                $arrReturnValue['response'] = 'You must be logged in order to get avatar.';
            } else if (!isset($_GET['user_id']) || !strlen($_GET['user_id'])) {
                $arrReturnValue['response'] = 'Invalid param user id.';
            } else {
                try {
                    $objYM = new Client($_SESSION['username'], $_SESSION['pwd'], APP_CONSUMER_KEY, APP_CONSUMER_SECRET);
                    $objYM->setTokens($_SESSION['ym_tokens'])
                          ->setSession($_SESSION['ym_session']);
                    $strAvatar = $objYM->fetchCustomAvatar($_GET['user_id']);
                    $arrReturnValue['status'] = 'success';
                    $arrReturnValue['response'] = array('user_id' => $_GET['user_id'], 'avatar_url' => $strAvatar);
                    /* check if token was renewed during last call */
                    if ($objYM->isTokenRenewed()) {
                        $_SESSION['ym_tokens'] = $objYM->getTokens();
                    }
                } catch (Exception $objEx) {
                    $arrReturnValue['response'] = 'Could not get user avatar.';
                    Logger::log($objEx->getMessage());
                }
            }
            break;
        /* GET NOTIFICATIONS */
        case 'get_notifications':
            if (!checkLocalLogin()) {
                $arrReturnValue['response'] = 'You must be logged in order to retrieve notifications.';
            } else {
                try {
                    $objYM = new Client($_SESSION['username'], $_SESSION['pwd'], APP_CONSUMER_KEY, APP_CONSUMER_SECRET);
                    $objYM->setTokens($_SESSION['ym_tokens']);
                    $objYM->setSession($_SESSION['ym_session']);
                    $notifications = $objYM->fetchNotifications($_SESSION['ym_notifications_seq']);
                    Logger::log(var_export($notifications, 1));
                    $arrAjaxResponse = array();
                    foreach ($notifications['responses'] as $notificationsResponse) {
                        foreach ($notificationsResponse as $notificationType => $notif) {
                            switch ($notificationType) {
                                case 'logOff':
                                    $arrAjaxResponse[] = array(
                                        'type' => $notificationType,
                                        'info' => array('buddy' => htmlspecialchars($notif['buddy'])),
                                    );
                                    break;
                                case 'buddyInfo':
                                    foreach ($notif['contact'] as $notifContact) {
                                        $info = array(
                                            'buddy' => htmlspecialchars($notifContact['sender']),
                                            'network' => isset($notifContact['network']) ? $notifContact['network'] : 'yahoo',
                                            'presenceMessage' => '',
                                            'presenceState' => '',
                                        );
                                        if (isset($notifContact['customDNDStatus'])) {
                                            if (0 == $notifContact['customDNDStatus'] && 99 == $notifContact['presenceState']) {
                                                $notifContact['presenceState'] = Client::USER_IS_ONLINE;
                                            } elseif (1 == $notifContact['customDNDStatus'] && 99 == $notifContact['presenceState']) {
                                                $notifContact['presenceState'] = Client::USER_IS_BUSY;
                                            } elseif (2 == $notifContact['customDNDStatus'] && 99 == $notifContact['presenceState']) {
                                                $notifContact['presenceState'] = Client::USER_IS_IDLE;
                                            }
                                        }
                                        switch ($notifContact['presenceState']) {
                                            case Client::USER_IS_ONLINE:
                                                $info['presenceState'] = 'online';
                                                $info['presenceMessage'] = isset($notifContact['presenceMessage']) ? $notifContact['presenceMessage'] : '';
                                                break;
                                            case Client::USER_IS_IDLE:
                                                $info['presenceState'] = 'idle';
                                                $info['presenceMessage'] = Client::$notificationsPresenceStatuses[Client::USER_IS_IDLE];
                                                break;
                                            case Client::USER_IS_BUSY:
                                                $info['presenceState'] = 'busy';
                                                $info['presenceMessage'] = isset($notifContact['presenceMessage']) ? $notifContact['presenceMessage'] : Client::$notificationsPresenceStatuses[Client::USER_IS_BUSY];
                                                break;
                                            default:
                                                if (array_key_exists($notifContact['presenceState'], Client::$notificationsPresenceStatuses)) {
                                                    $info['presenceState'] = 'online';
                                                    if (99 != $notifContact['presenceState']) {
                                                        $info['presenceMessage'] = isset($notifContact['presenceMessage']) ? $notifContact['presenceMessage'] : Client::$notificationsPresenceStatuses[$notifContact['presenceState']];
                                                    } else {
                                                        $info['presenceMessage'] = isset($notifContact['presenceMessage']) ? $notifContact['presenceMessage'] : '';
                                                    }
                                                }
                                        }

                                        $info['presenceMessage'] = htmlspecialchars($info['presenceMessage']);
                                        $arrAjaxResponse[] = array(
                                            'type' => $notificationType,
                                            'info' => $info,
                                        );
                                    }
                                    break;
                                case 'buddyStatus':
                                    $info = array(
                                        'buddy' => htmlspecialchars($notif['sender']),
                                        'network' => isset($notif['network']) ? $notif['network'] : 'yahoo',
                                    );
                                    if (isset($notif['customDNDStatus'])) {
                                        if (0 == $notif['customDNDStatus'] && 99 == $notif['presenceState']) {
                                            $notif['presenceState'] = Client::USER_IS_ONLINE;
                                        } elseif (1 == $notif['customDNDStatus'] && 99 == $notif['presenceState']) {
                                            $notif['presenceState'] = Client::USER_IS_BUSY;
                                        } elseif (2 == $notif['customDNDStatus'] && 99 == $notif['presenceState']) {
                                            $notif['presenceState'] = Client::USER_IS_IDLE;
                                        }
                                    }
                                    switch ($notif['presenceState']) {
                                        case Client::USER_IS_ONLINE:
                                            $info['presenceState'] = 'online';
                                            $info['presenceMessage'] = isset($notif['presenceMessage']) ? $notif['presenceMessage'] : '';
                                            break;
                                        case Client::USER_IS_IDLE:
                                            $info['presenceState'] = 'idle';
                                            $info['presenceMessage'] = Client::$notificationsPresenceStatuses[Client::USER_IS_IDLE];
                                            break;
                                        case Client::USER_IS_BUSY:
                                            $info['presenceState'] = 'busy';
                                            $info['presenceMessage'] = isset($notif['presenceMessage']) ? $notif['presenceMessage'] : Client::$notificationsPresenceStatuses[Client::USER_IS_BUSY];
                                            break;
                                        default:
                                            if (array_key_exists($notif['presenceState'], Client::$notificationsPresenceStatuses)) {
                                                $info['presenceState'] = 'online';
                                                if (99 != $notif['presenceState']) {
                                                    $info['presenceMessage'] = isset($notif['presenceMessage']) ? $notif['presenceMessage'] : Client::$notificationsPresenceStatuses[$notif['presenceState']];
                                                } else {
                                                    $info['presenceMessage'] = isset($notif['presenceMessage']) ? $notif['presenceMessage'] : '';
                                                }
                                            }
                                    }

                                    $info['presenceMessage'] = htmlspecialchars($info['presenceMessage']);
                                    $arrAjaxResponse[] = array(
                                        'type' => $notificationType,
                                        'info' => $info,
                                    );
                                    break;
                                case 'message':
                                    $arrAjaxResponse[] = array(
                                        'type' => $notificationType,
                                        'info' => array(
                                            'buddy' => htmlspecialchars($notif['sender']),
                                            'message' => htmlspecialchars($notif['msg']),
                                        ),
                                    );
                                    break;
                                case 'displayImage':
                                    $arrAjaxResponse[] = array(
                                        'type' => $notificationType,
                                        'info' => array(
                                            'buddy' => htmlspecialchars($notif['sender']),
                                            'url' => htmlspecialchars($notif['url']),
                                        ),
                                    );
                                    break;
                                case 'avatarImage':
                                case 'displayImagePrefs':
                                case 'fileTransferInvite':
                                case 'fileTransferReceive':
                                case 'sysMsg':
                                case 'disconnect':
                                default:
                                    // not yet supported
                            }
                        }
                    }
                    if (count($notifications['responses'])) {
                        $arrLastNotification = end($notifications['responses']);
                        $arrLastNotification = end($arrLastNotification);
                        $_SESSION['ym_notifications_seq'] = $arrLastNotification['sequence'] + 1;
                    }
                    $arrReturnValue['status'] = 'success';
                    $arrReturnValue['response'] = $arrAjaxResponse;

                    /* check if token was renewed during last call */
                    if ($objYM->isTokenRenewed()) {
                        $_SESSION['ym_tokens'] = $objYM->getTokens();
                    }
                } catch (Exception $objEx) {
                    $arrReturnValue['response'] = 'Could not get user notifications.';
                    Logger::log($objEx->getMessage());
                }
            }
            break;
        default:
            $arrReturnValue['response'] = 'Unknown ajax action.';
    }
}

ob_end_clean();
header('Content-Type: application/json;charset=utf-8');
echo json_encode($arrReturnValue);
