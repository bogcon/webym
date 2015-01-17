<?php
/**
 * Index route.
 * 
 * @author      Bogdan Constantinescu <bog_con@yahoo.com>
 * @link        GitHub  https://github.com/bogcon/webym
 * @license     New BSD License (http://opensource.org/licenses/BSD-3-Clause); see LICENSE.txt
 */
?>
<?php
session_start();
?>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WebYM</title>

        <link href="css/bootstrap.min.css" rel="stylesheet" />
        <link href="css/toastr.min.css" rel="stylesheet" />
        <link href="css/webym.css" rel="stylesheet" />
        

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body>
        <!-- begin login form -->
        <div id="webym-login" class="modal fade webym-login" data-backdrop="false">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Yahoo! Messenger</h4>
                    </div>
                    <div class="modal-body">
                        <p class="text-center"><img src="images/ym_logo.png" alt="" width="140" height="140" /></p>
                        <br />
                        <form role="form">
                            <div class="input-group">
                                <input name="username" id="username" type="text" class="form-control" placeholder="Username" value="<?php echo isset($_COOKIE['ym-remember-me']) ? htmlspecialchars($_COOKIE['ym-remember-me']) : ''; ?>" />
                            </div>
                            <br />
                            <div class="input-group">
                                <input name="password" id="password" type="password" class="form-control" placeholder="Password" value="" />
                            </div>
                            <br />
                            <div class="input-group">
                                <input type="checkbox" name="remember-me" id="remember-me" checked="checked" />
                                <label for="remember-me">Remember my ID</label><br />
                            </div>
                            <div class="input-group">
                                <input type="checkbox" name="invisible" id="invisible" />
                                <label for="invisible">Sign in as invisible to everyone</label><br />
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button id="signin-btn" type="button" class="btn btn-primary" onclick="logIn();" data-loading-text="Sign in...">Sign In</button>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
            <p class="footer">
                &COPY; Copyright 2013 <?php echo '2013' != date('Y') ? ' - ' . date('Y') : ''; ?> Bogdan Constantinescu
            </p>
        </div><!-- /.modal -->
        <!-- end login form -->

        <div class="container">
            <!-- begin left YM panel with avatar & buddies -->
            <div id="webym-ym" class="row webym-ym">
                <div id="webym-contacts" class="col-sm-4 col-md-4 col-lg-3 webym-contacts">
                    <h3 class="clearfix">Yahoo! Messenger<img id="logout" src="images/logout.png" class="pull-right" data-toggle="tooltip" data-placement="bottom" title="Logout" width="16" height="16" onclick="logOut()" alt=""/></h3>
                    <div id="webym-profile">
                        <div class="row">
                            <div class=" col-xs-8 nopadr">
                                <div class="input-group mb05">
                                    <select class="form-control input-sm" id="state-select" name="state-select" onchange="changeStatus()">
                                        <option value="online" class="online-option">Available</option>
                                        <option value="busy" class="busy-option">Busy</option>
                                        <option value="invisible" class="invisible-option">Invisible</option>
                                        <option value="idle" class="idle-option">Away</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <input type="text" class="state-message form-control input-sm" id="state-message" placeholder="Enter status message here..." name="state-message" value="" />
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <img id="webym-avatar" class="thumbnail" width="72" height="72" alt="" src="http://msgr.zenfs.com/msgrDisImg/EKGDITHZ56MEQEXT2R4X7BV2KY">
                            </div>
                        </div>
                    </div>
                    <ul id="webym-buddies" class="media-list webym-buddies"></ul>
                </div>
                <!-- end left YM panel with avatar & buddies -->
                
                <!-- begin YM panel with conversations tabs -->
                <div id="webym-talk" class="col-sm-8 col-md-8 col-lg-9" role="tablist">
                    <ul class="nav nav-tabs" id="webym-convestations-tab"></ul>
                    <div class="tab-content" id="webym-convestations-tab-content"></div>
                </div>
                <!-- begin YM panel with conversations tabs -->
            </div>
        </div>
        
        <script type="text/javascript" src="js/jquery-1.10.2.min.js"></script>
        <script type="text/javascript" src="js/jquery.color-2.1.2.min.js"></script> <!-- needed for bgcolor animation to work -->
        <script type="text/javascript" src="js/bootstrap.min.js"></script>
        <script type="text/javascript" src="js/toastr.min.js"></script> <!-- displays nice toast message -->
        <script type="text/javascript" src="js/webym.js"></script>
    </body>
</html>