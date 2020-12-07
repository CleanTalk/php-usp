<?php

require_once CT_USP_VIEW. 'check_requirements.php';

use Cleantalk\USP\Variables\Server;

// Exit if accessed directly.
if ( ! defined( 'CT_USP_ROOT' ) ) {
    header('HTTP/1.0 403 Forbidden');
    exit ();
}

$usp = \Cleantalk\USP\Common\State::getInstance();

?>

<body class="fade-in">
    <div class="container" id="layout-block">
        <div class="row">
            <div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4 setup-box clearfix animated flipInY">

                <div class="page-icon animated bounceInDown">
                    <img src="img/logo.png" alt="Cleantalk logo" />
                </div>
                <div class="logo">
                    <h3><strong> - UniForce - </strong></h3>
                    <h3> - Universal Security Plugin - </h3>
                </div>
                <hr />

                <div class="setup-form" style="text-align: center;">

                    <!-- Start Success Box -->
                    <div class="alert alert-success alert-dismissible fade in" style="display:none; word-wrap: break-word;" role="alert">
                        <strong style="text-align: center; display: block;">Success!</strong>
                        <br />
                        <p>Enter your <a class="underlined" href="https://cleantalk.org/my/">CleanTalk dashboard</a> to view statistics.</p>
                        <br />
                        <p>UniForce dashboard is <?php echo '<a href="' . CT_USP_URI . '">here</a>'; ?>.</p>
                        <br />
                        <p>Password was sent to your email.</p>
                    </div>
                    <!-- End Success box -->

                    <?php if( CT_USP__NO_SQL ): ?>
                        <!-- Warning box -->
                        <div class="alert alert-warning alert-dismissible fade in" role="alert">
                            <button type="button" class="close" > &times;</button>
                            <p id='error-msg'>Warning: Couldn't connect to cloud SQL. Malware scanner will use local database to store scan results.</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Start Error box -->
                    <div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
                        <button type="button" class="close" > &times;</button>
                        <p id='error-msg'></p>
                    </div>
                    <!-- End Error box -->

                    <!-- Start Installation form -->
                    <form action = 'javascript:void(null);' method="post" id='setup-form'>
                        <div style="text-align: center">
                            <input type="text" placeholder="E-mail" class="input-field" name="access_key_field" required style="display: inline;"/>
                            <img class="preloader" src="img/preloader.gif" style="display: none;" alt="">
                        </div>
                        <input type="password" name="admin_password" class="input-field" placeholder="Password" />
                        <p class="text-center --hide" id='password_requirements'><small>Password requirements are 4 symbols minimum, and no spaces.</small></p>
                        <p>
                            <button type="button" class="btn" id="show_more_btn" style="background-color:transparent">
                                Advanced configuration (optional)
                                <img  class ="show_more_icon" src="img/expand_more.png" alt="Show more" style="width:24px; height:24px;"/>
                            </button>
                        </p>
                        <div class ="advanced_conf">
                            <p><small>Additional scripts</small>&nbsp
                                <img data-toggle="tooltip" data-placement="top" src="img/help_icon.png" title="Universal Security plugin will write protection code to index.php file by default. If your contact or registration contact forms are located in different files/scripts, list them here separated by commas. Example: register.php, contact.php" style="width:10px; height:10px;" alt="">
                            </p>
                            <input type="text" class="input-field" name="addition_scripts" />
                        </div>
                        <button type="submit" class="btn btn-setup" disabled>Install</button>
	
	                    <?php if( CT_USP__NO_SQL ): ?>
                            <input type="hidden" name="no_sql" value="1" />
	                    <?php endif; ?>
                     
                    </form>

                    <div class="setup-links">
                        <a href="https://cleantalk.org/publicoffer" target="_blank">
                            License agreement
                        </a>
                        <br />
                        <a href="https://cleantalk.org/register?platform=uniforce&website=<?php echo Server::get( 'SERVER_NAME' ); ?>&product_name=security" target="_blank">
                            Don't have an account? <strong>Create here!</strong>
                        </a>
                    </div>
                    <!-- End Installation form -->

                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <p class="footer-text"><small>It is highly recommended to create a backup before installation</small></p>
            </div>
        </div>
    </div>

    <footer class="container">

    </footer>