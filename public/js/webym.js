/**
 * JS File for WebYM.
 * 
 * @author      Bogdan Constantinescu <bog_con@yahoo.com>
 * @link        GitHub  https://github.com/bogcon/webym
 * @license     New BSD License (http://opensource.org/licenses/BSD-3-Clause); see LICENSE.txt
 */
"use strict";

var intNotificationTimeout = 0;
var intNotificationInterval = 0;

jQuery(document).ready(function() {
    /* show login form */
    jQuery('#webym-login').modal('show');
   
    /* sign in user on enter keypress in login form */
    jQuery("#username, #password").bind('keypress', function(event) {
        var intCode = (event.keyCode ? event.keyCode : event.which);
        if (13 === intCode) { // user pressed "Enter"
            logIn();
        }
    });
   
   /* toastr options - used for notifications */
   toastr.options = {    
        "closeButton": true,
        "debug": false,
        "positionClass": "toast-bottom-right",
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };
    
    /* send message */
    jQuery('#webym-convestations-tab-content').delegate('.writeMessage', 'keypress', function(event) {
        var intCode = (event.keyCode ? event.keyCode : event.which);
        if (13 !== intCode) { // user pressed "Enter"
            return;
        }
        var strTargetId = jQuery(this).parent('div').parent('div').attr('id').replace(/^tab_content_/, '');
        var strMessage  = jQuery.trim(jQuery(this).val());
        if (strTargetId.length > 0 && strMessage.length > 0) {
            sendMessage(strTargetId, strMessage);
            var objMessengesContainer = jQuery(this).parent('div').parent('div').find('.messagesContainer');
            objMessengesContainer.html(
                objMessengesContainer.html() +
                '(' + getCurrentDate() + ') ' +
                '<strong>Me:</strong> ' + strMessage + '<br />'
            );
            objMessengesContainer.prop('scrollTop', objMessengesContainer.prop('scrollHeight'));
            jQuery(this).val('');
        }
    });
    
    /* change image on mouse over for logout btn */
    jQuery('#logout').on('mouseover', function() {
       jQuery(this).attr('src', 'images/logout2.png'); 
    }).on('mouseout', function() {
        jQuery(this).attr('src', 'images/logout.png'); 
    });
    
    /* show chat window on dbl click on a buddy */
    jQuery('#webym-buddies').delegate('.ycontact', 'dblclick', function() {
        var strContactId = jQuery(this).attr('id');
        if (jQuery('li[id="tab_' + strContactId + '"]').length < 1) {
            jQuery('#webym-convestations-tab-content').append(
                    '<div class="tab-pane" id="tab_content_' + strContactId + '">' +
                    '<div class="messagesContainer"></div>' +
                    '<div class="input-group">' +
                    '<input maxlength="2000" class="writeMessage form-control" placeholder="Enter message here..." value="" />' +
                    '</div>' +
                    '</div>'
            );
            var strContactName = jQuery(this).find('.ycontact-nickname strong').text();
            jQuery('#webym-convestations-tab').append(
                    '<li id="tab_' + strContactId + '">' +
                    '<a href="#tab_content_' + escapeJQuerySelectors(strContactId) + '" data-toggle="tab">' + strContactName + ' <button aria-hidden="true" class="close ml05" type="button" onclick="closeTab(\'' + strContactId + '\')">&times;</button></a>' +
                    '</li>'
            );
            // append "Is offline" message
            if (jQuery('li[id="' + strContactId + '"]').hasClass('offline')) {
                var objMessengesContainer = jQuery('div[id="tab_content_' + strContactId + '"]').find('.messagesContainer');
                objMessengesContainer.html(
                    objMessengesContainer.html() +
                    '(' + getCurrentDate() + ') ' +
                    '<i>' + strContactName + ' appears to be offline.</i><br />'
                );
                objMessengesContainer.prop('scrollTop', objMessengesContainer.prop('scrollHeight'));
            }
        }
        // focus the new tab
        if (!jQuery('li[id="tab_' + strContactId + '"]').data('doNotSetTabAsActive')) { // doNotSetTabAsActive is not set
            jQuery('li[id="tab_' + strContactId + '"] a').trigger('click');
        }
    });
    
    /* toogle buddies on click on a group */
    jQuery('#webym-buddies').delegate('.groupname', 'click', function() {
        if (jQuery(this).next().is('.groupcontacts')) {
            jQuery(this).next().slideToggle();
        }
    });
    
    /* status changes */
    jQuery('#state-message').keypress(function(event) {
        var intCode = (event.keyCode ? event.keyCode : event.which);
        if (13 === intCode) { // user pressed "Enter"
            changeStatus();
        }
    });
    
    /* stop tab blink animation */
    jQuery('#webym-talk').delegate('li[id^="tab_"]', 'click', function(event) {
        jQuery(this).find('a').stop(true).css('background-color', '#FFF');
    });
    jQuery('#webym-talk').delegate('div[id^="tab_content_"]', 'mouseenter', function(event) {
        jQuery('a[href="#' + jQuery(this).attr('id') + '"]').stop(true).css('background-color', '#FFF');
    });
});
                     
                
                
/**
 * Logs out a user.  
 */     
function logOut()
{
    jQuery.ajax({
        type: 'POST',
        url: 'ajax.php',
        data: {
            'ajax_action': 'logout'
        },
        dataType: 'json',
        success: function (objResponse) {
            if ('success' === objResponse.status) {
                jQuery('#webym-ym').hide(); // hide yahoo messenger window and reopen login window
                jQuery('#webym-login').modal('show');
                jQuery('#webym-buddies').html(''); // reset buddies list
                jQuery('#webym-convestations-tab').html(''); // reset conversations
                jQuery('#webym-convestations-tab-content').html('');
                jQuery(document).unbind('keydown'); // cancel CTRL + G event
                jQuery(window).unbind('beforeunload'); // cancel browser close confirmation
                /* stop getting notifications */
                if (0 !== intNotificationTimeout) {
                    clearTimeout(intNotificationTimeout);
                    intNotificationTimeout = 0;
                }                
                if (0 !== intNotificationInterval) {
                    clearInterval(intNotificationInterval);
                    intNotificationInterval = 0;
                }
            } else {
                alert(objResponse.response);
            }
        },
        error: function () {
            alert('Could not log out.');
        }
    });
}
   
   

/**
 * Logs in a user.
 */
function logIn()
{
    /* validate form */
    var blnFormValid   = true;
    var objUsername    = jQuery('#username');
    var objPassword    = jQuery('#password');
    var intRememberMe  = jQuery('#remember-me').is(':checked') ? 1 : 0;
    var intInvisible   = jQuery('#invisible').is(':checked') ? 1 : 0;

    objUsername.removeClass('input-error');
    objPassword.removeClass('input-error');
    jQuery('span.error').remove();

    if (objUsername.val().length < 3 || objUsername.val().length > 97) {
        showError(objUsername, 'Invalid username.');
        blnFormValid = false;
    }
    if (objPassword.val().length < 3 || objPassword.val().length > 32) {
        showError(objPassword, 'Invalid password.');
        blnFormValid = false;
    }
    if (/.*@.*@.*/.test(objUsername.val())) {
        showError(strUsername, 'Invalid username.');
        blnFormValid = false;
    }

    if (!blnFormValid) {
        return;
    }
    jQuery('#signin-btn').button('loading');
    
    jQuery.ajax({
        type: 'POST',
        url: 'ajax.php',
        data: {
            'ajax_action': 'login',
            'username': objUsername.val(),
            'password': objPassword.val(),
            'remember_me': intRememberMe,
            'invisible': intInvisible
        },
        dataType: 'json',
        success: function (objResponse) {
            if ('success' === objResponse.status) {
                jQuery('#webym-login').modal('hide');
                jQuery('#webym-ym').fadeIn(2000);
                if (!intRememberMe) {
                    objUsername.val('');
                }
                objPassword.val('');
                
                populateContacts(objResponse.response['contacts']);
                
                /* show user 's avatar */
                jQuery('#webym-avatar').attr('src', objResponse.response['user_avatar']);
                
                /* add extra italic class if logged in as Invisible */
                if (intInvisible) {
                    jQuery('#webym-buddies').addClass('italic');
                    jQuery('#state-select').val('invisible');
                } else {
                    jQuery('#state-select').val('online');
                }
                
                /* show/hide offline buddies on CTRL + G key combination */
                jQuery(document).bind('keydown', function(event) {
                    if (event.ctrlKey && 71 === event.keyCode) {
                        if (!jQuery('li.offline').is(':visible')) {
                            jQuery('li.offline').show('fast');
                        } else {
                            jQuery('li.offline').hide('fast');
                        }
                    }
                });
                
                /* start getting notifications after a while, wait a litlle for avatars to get loaded */
                intNotificationTimeout = setTimeout(function() {
                    intNotificationInterval = setInterval(function() {
                        getNotifications();
                    }, 7000);
                }, 25000);
                
                /* log out the user on browser close */
                jQuery(window).bind('beforeunload', function() {
                    console.log('bind unload');
                    if (jQuery('#webym-ym').is(':visible')) { // user is logged in
                        if (confirm("Are you sure you want to sign off and quit the application?")) {
                            logOut();
                            return true;
                        }
                        return false;
                    }
                    return true;
                });
            } else {
                alert(objResponse.response);
            }
        },
        error: function() {
            alert("Could not log in.");
        },
        complete: function() {
            jQuery('#signin-btn').button('reset');
        }
    });
}



/**
 * Populate contacts list.
 * @param   arrGroups   array   The array with groups and contacts info.
 */            
function populateContacts(arrGroups)
{
    var strContactsList = '';
    var strGroupName, intContactKey;
    var strContactsHtml = '';
    var intGroupOfflineContactsCount = 0;
    var arrOnlineIds = [];
    var arrOfflineIds = [];
    
    for (strGroupName in arrGroups) {
        strContactsHtml = '';
        intGroupOfflineContactsCount = 0;
        for (intContactKey in arrGroups[strGroupName]) {
            strContactsHtml += '<li class="media ycontact ' + arrGroups[strGroupName][intContactKey]['state'] + '" id="' + arrGroups[strGroupName][intContactKey]['id'] + '">' + "\n";
            strContactsHtml += "\t" + '<div class="pull-right">' + "\n";
            strContactsHtml += "\t\t" + '<img class="avatar media-object" src="" width="44" height="44" />' + "\n";
            strContactsHtml += "\t" + '</div>' + "\n";
            strContactsHtml += "\t" + '<div class="media-body">' + "\n";
            strContactsHtml += "\t\t" + '<div class="ycontact-nickname" title="' + arrGroups[strGroupName][intContactKey]['name'] + '">' + "\n";
            strContactsHtml += "\t\t\t" + '<img class="ycontact-statusimg" src="images/' + arrGroups[strGroupName][intContactKey]['state'] + '.png" alt="" />' + "\n";
            strContactsHtml += "\t\t\t" + '<strong>' + arrGroups[strGroupName][intContactKey]['name'] + '</strong>' + "\n";
            strContactsHtml += "\t\t" + '</div>' + "\n";
            strContactsHtml += "\t\t" + '<div class="ycontact-statusmessage" title="' + arrGroups[strGroupName][intContactKey]['status'] + '">' + arrGroups[strGroupName][intContactKey]['status'] + '</div>' + "\n";
            strContactsHtml += "\t" + '</div>';
            strContactsHtml += '</li>';
            if ('offline' === arrGroups[strGroupName][intContactKey]['state']) {
                intGroupOfflineContactsCount += 1;
                arrOfflineIds.push(arrGroups[strGroupName][intContactKey]['id']);
            } else {
                arrOnlineIds.push(arrGroups[strGroupName][intContactKey]['id']);
            }
        }
        strContactsList += '<li class="media groupname"><h5>' + strGroupName + ' (<span>' + (arrGroups[strGroupName].length - intGroupOfflineContactsCount) + '</span>/' + arrGroups[strGroupName].length + ')</h5></li>' + "\n";
        strContactsList += '<li class="media groupcontacts">';
        if ('' !== strContactsHtml) {
            strContactsList += '<ul class="media-list">' + strContactsHtml + '</ul>';
        }
        strContactsList += '</li>';
    }
    jQuery('#webym-buddies').html(strContactsList);
    
    /* get avatar for each buddy, online users have priority */
    for (intContactKey in arrOnlineIds) {
        getAvatar(arrOnlineIds[intContactKey]);
    }
    for (intContactKey in arrOfflineIds) {
        getAvatar(arrOfflineIds[intContactKey]);
    }
}



/**
 * Fetch a user 's avatar.
 * @param   strContactId    string  User 's id.
 */
function getAvatar(strContactId)
{
    jQuery.ajax({
        type: 'GET',
        url: 'ajax.php',
        data: {
            'ajax_action': 'get_avatar',
            'user_id': strContactId
        },
        dataType: 'json',
        success: function (objResponse) {
            if ('success' === objResponse.status) {
                jQuery('li[id="' + objResponse.response['user_id'] + '"]').find('.avatar').attr('src', objResponse.response['avatar_url']);
            }
        }
    });
}



/**
 * Retrieve notifications from yahoo servers.            
 */            
function getNotifications()
{
    jQuery.ajax({
        type: 'GET',
        url: 'ajax.php',
        data: {
            'ajax_action': 'get_notifications'
        },
        dataType: 'json',
        success: function (objResponse) {
            if ('success' !== objResponse.status) {
                return;
            }
            for (var intKey in objResponse.response) {
                switch (objResponse.response[intKey]['type']) {
                    case 'logOff':
                        var objContact = jQuery('li[id="' + objResponse.response[intKey]['info']['buddy'] + '"]');
                        if (objContact.length) {
                            /* update presence state, presence message */
                            objContact.removeClass('idle')
                                .removeClass('online')
                                .removeClass('busy')
                                .addClass('offline');
                            objContact.find('.ycontact-statusimg').attr('src', 'images/offline.png');
                            objContact.find('.ycontact-statusmessage').text('');
                            var strContactName = objContact.find('.ycontact-nickname strong').text();

                            /* show notification on the bottom of ym window */
                            toastr.info(strContactName + ' is offline.');

                            if (jQuery('li[id="tab_' + objResponse.response[intKey]['info']['buddy'] + '"]').length > 0) {
                                /* add message "X has signed out" on chat window */
                                var objMessengesContainer = jQuery('div[id="tab_content_' + objResponse.response[intKey]['info']['buddy'] + '"]').find('.messagesContainer');
                                objMessengesContainer.html(
                                    objMessengesContainer.html() +
                                    '(' + getCurrentDate() + ') ' +
                                    '<i>' + strContactName + ' has signed out.</i>' + '<br />'
                                );
                                objMessengesContainer.prop('scrollTop', objMessengesContainer.prop('scrollHeight'));
                            }

                            /* decrement group online users count */
                            var objOnlineCountHolder = objContact.parents('li').first().prev('.groupname').first().find('span').first();
                            var onlineCount = parseInt(objOnlineCountHolder.text());
                            if (!isNaN(onlineCount) && onlineCount > 0) {
                                objOnlineCountHolder.text(--onlineCount);
                            }
                        }
                        break;                
                    case 'buddyInfo': // intentionally omitted break
                    case 'buddyStatus':
                        var objContact = jQuery('li[id="' + objResponse.response[intKey]['info']['buddy'] + '"]');
                        if (objContact.length) {
                            /* update presence state, presence message */
                            var oldState = 'online';
                            if (objContact.hasClass('idle')) {
                                oldState = 'idle';
                            } else if (objContact.hasClass('busy')) {
                                oldState = 'busy';
                            } else if (objContact.hasClass('offline')) {
                                oldState = 'offline';
                            } else if (objContact.hasClass('online')) {
                                oldState = 'online';
                            }

                            objContact.removeClass(oldState)
                                .addClass(objResponse.response[intKey]['info']['presenceState']);
                            objContact.find('.ycontact-statusimg').attr('src', 'images/' + objResponse.response[intKey]['info']['presenceState'] + '.png');
                            objContact.find('.ycontact-statusmessage').text(objResponse.response[intKey]['info']['presenceMessage']);

                            if ('offline' === oldState && 'offline' !== objResponse.response[intKey]['info']['presenceState']) { // user is online
                                var strContactName = objContact.find('.ycontact-nickname strong').text();
                                /* show notification on the buttom of ym window */
                                toastr.info(strContactName + ' is online.');

                                /* add message "X is online" on chat window */
                                if (jQuery('li[id="tab_' + objResponse.response[intKey]['info']['buddy'] + '"]').length > 0) {
                                    var objMessengesContainer = jQuery('div[id="tab_content_' + objResponse.response[intKey]['info']['buddy'] + '"]').find('.messagesContainer');
                                    objMessengesContainer.html(
                                        objMessengesContainer.html() +
                                        '(' + getCurrentDate() + ') ' +    
                                        '<i>' + strContactName + ' is online.</i>' + '<br />'
                                    );
                                    objMessengesContainer.prop('scrollTop', objMessengesContainer.prop('scrollHeight'));
                                }

                                /* increment group online users count */
                                var objOnlineCountHolder = objContact.parents('li').first().prev('.groupname').first().find('span').first();
                                var onlineCount = parseInt(objOnlineCountHolder.text());
                                if (!isNaN(onlineCount) && onlineCount >= 0) {
                                    objOnlineCountHolder.text(++onlineCount);
                                }
                            }
                        }
                        break;             
                    case 'message':
                        var strContactId = objResponse.response[intKey]['info']['buddy'];
                        var strMsg = objResponse.response[intKey]['info']['message'];
                        /* open chat window if not opened */
                        jQuery('li[id="tab_' + strContactId + '"]').data('doNotSetTabAsActive', true);
                        if (jQuery('li[id="' + strContactId + '"]').length > 0) {
                            jQuery('li[id="' + strContactId + '"]').dblclick();
                        } else if (!JQuery('li[id="tab_' + strContactId + '"]')) { // maybe a spammer ?
                            jQuery('#webym-convestations-tab-content').append(
                                    '<div class="tab-pane" id="tab_content_' + strContactId + '">' +
                                        '<div class="messagesContainer"></div>' +
                                        '<div class="input-group">' +
                                        '<input maxlength="2000" class="writeMessage form-control" placeholder="Enter message here..." value="" />' +
                                        '</div>' +
                                    '</div>'
                            );
                            var strContactName = jQuery(this).find('.ycontact-nickname strong').text();
                            jQuery('#webym-convestations-tab').append(
                                    '<li id="tab_' + strContactId + '">' +
                                    '<a href="#tab_content_' + escapeJQuerySelectors(strContactId) + '" data-toggle="tab">' + strContactId + ' <button aria-hidden="true" class="close ml05" type="button" onclick="closeTab(\'' + strContactId + '\')">&times;</button></a>' +
                                    '</li>'
                            );
                        }
                        /* add message to the chat window */
                        var objMessengesContainer = jQuery('div[id="tab_content_' + strContactId + '"]').find('.messagesContainer');
                        objMessengesContainer.html(
                            objMessengesContainer.html() +
                            '(' + getCurrentDate() + ') ' +
                            '<strong>' + strContactId + ':</strong> ' + strMsg + '<br />'
                        );
                        objMessengesContainer.prop('scrollTop', objMessengesContainer.prop('scrollHeight'));
                        /* blink tab if not focused */
                        if (!jQuery('div[id="tab_content_' + strContactId + '"]').find('.writeMessage').is(':focus')) {
                            animateBackground(strContactId);
                        }
                        break;
                    case 'displayImage':
                        /* update buddy 's avatar */
                        var objContact = jQuery('li[id="' + objResponse.response[intKey]['info']['buddy'] + '"]');
                        if (objContact.length) {
                            objContact.find('.avatar').attr('src', objResponse.response[intKey]['info']['url']);
                        }
                    break;
                    default:
                        // unsupported operation, do nothing
                }
            }
        }
    });
}
            


/**
* Show error messages.
* @param   objInput        object  The jQuery input object.
* @param   strErrorText    string  The error message to display.
*/            
function showError(objInput, strErrorText)
{
    objInput.addClass('input-error');
    jQuery('span#error-' + objInput.attr('id')).remove();
    objInput.after('<span id="error-' + objInput.attr('id') + '" class="error">' + strErrorText + '</span>');
}
            


/**
* Send a message to another user.
* @param   strTargetId     string      The ID of the user to send message to.
* @param   strMsg          string      The message to send.
*/            
function sendMessage(strTargetId, strMsg)
{
    jQuery.ajax({
        type: 'POST',
        url: 'ajax.php',
        data: {
            'ajax_action': 'send_message',
            'target_id': strTargetId,
            'message': strMsg
        },
        dataType: 'json'
    });
}



/**
 * Change presence status.
 */
function changeStatus()
{
    jQuery.ajax({
        type: 'POST',
        url: 'ajax.php',
        data: {
            'ajax_action': 'change_status',
            'state': jQuery('#state-select').val(),
            'state_message': jQuery('#state-message').val()
        },
        dataType: 'json',
        success: function (objResponse) {
            if ('success' === objResponse.status) {
                if ('invisible' !== jQuery('#state-select').val()) {
                    jQuery('#webym-buddies').removeClass('italic');
                } else {
                    jQuery('#webym-buddies').addClass('italic');
                }
            }
        }
    });
}



/**
* Retrieve current date.
* @return  string  Date is formatted as in PHP YYYY-mm-dd H:i:s.
*/
function getCurrentDate()
{
    var objNow = new Date();
    var intHours = objNow.getHours();
    var intMinutes = objNow.getMinutes();
    var intSeconds = objNow.getSeconds();
    var intYear = objNow.getFullYear();
    var intMonth = objNow.getMonth();
    var intDay = objNow.getDay();
    
    var strNow = intYear
        + '-' + (intMonth < 10 ? "0" + intMonth : intMonth)
        + '-' + (intDay < 10 ? "0" + intDay : intDay)
        + ' ' + (intHours < 10 ? "0" + intHours : intHours)
        + ':' + (intMinutes < 10 ? "0" + intMinutes : intMinutes)
        + ':' + (intSeconds < 10 ? "0" + intSeconds : intSeconds);
    return strNow;       
}



/**
 * Blinks chat tab background.
 * @param   strContactId    string  Buddy 's id.
 */
function animateBackground(strContactId)
{
    jQuery('li[id="tab_' + strContactId + '"] a').animate({backgroundColor: '#FFF' }, 400);
    jQuery('li[id="tab_' + strContactId + '"] a').animate({backgroundColor: '#A3FFBF' }, 700, function() {
        animateBackground(strContactId);
    });
}



/**
 * Close conversation tab.
 * @param strContactId  string  Id of the buddy
 */
function closeTab(strContactId)
{
    var tab = jQuery('li[id="tab_' + strContactId + '"]');
    var isActive = tab.hasClass('active');
    tab.remove();
    jQuery('div[id="tab_content_' + strContactId + '"]').remove();
    if (isActive) { // set remaining first tab as active
        var newActiveTab = jQuery('li[id^="tab_"]').first();
        if (newActiveTab.length) {
            newActiveTab.addClass('active');
            jQuery(newActiveTab.find('a').first().attr('href')).addClass('active');
        }
    }
}



/**
 * Escapes jQuery special chars !"#$%&'()*+,./:;<=>?@[\]^`{|}~
 * @param   strString  string   String to escape chars for.
 * @returns string with jQuery special chars escaped.
 */
function escapeJQuerySelectors(strString)
{
    var objRegex = /(\\|\!|\"|\#|\$|\%|\&|\'|\(|\)|\*|\+|\,|\.|\/|\:|\;|\<|\=|\>|\?|\@|\[|\]|\^|\`|\{|\}|\||\~)/g;
    return strString.replace(objRegex, "\\$1");
}
