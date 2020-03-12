<?php

use Cleantalk\Common\State;
use Cleantalk\Variables\Server;

// Exit if accessed directly.
if ( ! defined( 'CT_USP_ROOT' ) ) {
	header('HTTP/1.0 403 Forbidden');
	exit ();
}

?>

<body class="fade-in">
	<div class="container" id="layout-block">
		<div class="row">
			<div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4 setup-box clearfix animated flipInY">

                <!-- Icon and title -->
                <div class="page-icon animated bounceInDown">
                    <img src="img/logo.png" alt="Cleantalk logo" />
                </div>
                <div class="logo">
                    <h3> - Universal Security Plugin - </h3>
                </div>

                <hr />

				<!-- Login form -->
                <div class="setup-form" style="text-align: center;">

                    <!-- Start Error box -->
                    <div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
                        <button type="button" class="close" > &times;</button>
                        <p id='error-msg'></p>
                    </div>
                    <!-- End Error box -->

                    <form action = 'javascript:void();' method="post" id='login-form'>

                        <input type="text" placeholder="Access key<?php if( State::getInstance()->email && State::getInstance()->password ) echo ' or e-mail'; ?>" class="input-field" name="login" required/>

                        <?php if( State::getInstance()->password ) : ?>
                            <input type="password" placeholder="Password" class="input-field" name="password"/>
                        <?php endif; ?>
                        <button type="submit" name="action" value="login" class="btn btn-setup" id="btn-login">Login</button>
                        <p>Don't know your access key? Get it <a href="https://cleantalk.org/my" target="_blank">here</a>.</p>

                    </form>

                </div>
			</div>
		</div>
	</div>