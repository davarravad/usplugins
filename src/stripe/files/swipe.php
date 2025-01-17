<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


//This block of code will allow only https connections
include "../plugin_info.php";
pluginActive($plugin_name);
$use_sts = true;

// iis sets HTTPS to 'off' for non-SSL requests
if ($use_sts && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
  header('Strict-Transport-Security: max-age=31536000');
} elseif ($use_sts) {
  header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], true, 301);
  // we are in cleartext at the moment, prevent further execution and output
  die("Your connection is not secure.");
}

//end stripe-specific security statements

//typical userspice includes
require_once '../../../../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/header.php';
require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';

if (!securePage($_SERVER['PHP_SELF'])){die();}

?>

<!-- The generic stripe javascript hosted on stripe.com and specific jquery -->
<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

<!-- This stylesheet is only needed if you're doing a card mag stripe swipe -->
<link rel="stylesheet" type="text/css" href="<?=$us_url_root?>usersc/plugins/stripe/assets/stripe.css" />

<?php
//The PHP class for stripe.com
require_once $abs_us_root.$us_url_root.'usersc/plugins/stripe/assets/stripe-php/init.php';
?>

<div id="page-wrapper">
  <div class="container-fluid">
    <?php
    if ($_POST) {
      $token = $_POST['csrf'];
      if(!Token::check($token)){
        include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
      }
      $fname = Input::get('fname');
      $lname = Input::get('lname');
      $fullname = $fname." ".$lname;
      $email = Input::get('email');
      $rawAmount = Input::get('amount');
      $amount = $rawAmount * 100; //note that stripe expects the payment amount to be in pennies so we're converting it
      $note = Input::get('note');

      \Stripe\Stripe::setApiKey($settings->stripe_private);

      // Get the credit card details submitted by the form

      $token = $_POST['stripeToken'];
      // Add email address to metadata to make it searchable in the dashboard

      $metadata = array(
        "cardholder_name"=>$fullname,
        "email"=>$email,
        "by"=>$user->data()->id,
        "note"=>$note,
      );


      // Add email address to description for risk scoring
      $description = $settings->site_name;


      // Create the charge on Stripe's servers - this will charge the user's card
      try {
        $charge = \Stripe\Charge::create(array(
          "amount" => $amount, // amount in cents
          "currency" => "usd",
          "source" => $token,
          "description" => $description,
          "metadata" => $metadata,
        ));
        $chargeID = $charge['id']; //from the stripe API

        $fields = array(
          'user'             => $user->data()->id,
          'amount'           => $rawAmount,
          'email'            => $email,
          'notes'            => $note,
          'fname'            => $fname,
          'lname'            => $lname,
          'charge_id'        => $chargeID,
          'card_type'        => Input::get('type'),
        );
        $db->insert('stripe_transactions',$fields);
        logger($user->data()->id,"User","Credit Card - $fullname.");
        bold("Card processed successfully");
      } catch(\Stripe\Error\Card $e) {
        // Since it's a decline, \Stripe\Error\Card will be caught
        $body = $e->getJsonBody();
        $err  = $body['error'];
        print('Status is:' . $e->getHttpStatus() . "\n");
        print('Type is:' . $err['type'] . "\n");
        print('Code is:' . $err['code'] . "\n");
        // param is '' in this case
        print('Param is:' . $err['param'] . "\n");
        print('Message is:' . $err['message'] . "\n");
      } catch (\Stripe\Error\RateLimit $e) {
        // Too many requests made to the API too quickly
      } catch (\Stripe\Error\InvalidRequest $e) {
        // Invalid parameters were supplied to Stripe's API
      } catch (\Stripe\Error\Authentication $e) {
        // Authentication with Stripe's API failed
        // (maybe you changed API keys recently)
      } catch (\Stripe\Error\ApiConnection $e) {
        // Network communication with Stripe failed
      } catch (\Stripe\Error\Base $e) {
        // Display a very generic error to the user, and maybe send
        // yourself an email
      } catch (Exception $e) {
        // Something else happened, completely unrelated to Stripe
      }
    }
    $token = Token::generate();
    ?>
    <div class="row">
      <div class="col-xs-3"></div>
      <div class="col-xs-6">
        <form action="" method="POST" id="payment-form">
            <input type="hidden" name="csrf" $value=<?=$token;?>" />
          <span class="payment-errors"></span>
          <div class="form-row">
            <label>
              <span>Amount to charge</span>
              <input class="form-control" type = 'number' min="0.01" step="0.01" size="10" name="amount" value="" />
            </label>
          </div>
          <div class="form-row">
            <label>
              <span>Card Number</span>
              <input class="form-control" type="text" size="20" data-stripe="number" value="" id="account" />
            </label>
          </div>
          <label>
            <span>Card Type</span>
            <select class="form-control" name="type" id="type">
              <option value="">(Select card type)</option>
              <option value="amex">American Express</option>
              <option value="visa">Visa</option>
              <option value="mastercard">MasterCard</option>
              <option value="discover">Discover</option>
            </select></label>

            <div class="form-row">
              <label>
                <span>Expiration Month(MM)</span>
                <input class="form-control"type="text" size="2" data-stripe="exp-month" id="expMonth" value="" />
              </label>
              <span> / </span>
              <label>
                <span>Expiration Year(YY)</span>
                <input class="form-control" type="text" size="2" data-stripe="exp-year" value="" id="expYear" />
              </label>
            </div>
            <div class="form-row">
              <label>
                <span>Cardholder First Name</span>
                <input class="form-control" type="text" size="50" name="firstname" value="" id="firstName"/>
              </label>
              <label>
                <span>Cardholder Last Name</span>
                <input class="form-control" type="text" size="50" name="lastname" data-stripe="name" value="" id="lastName"/>
              </label>
            </div>

            <div class="form-row">
              <label>
                <span><font color="red">CVC</font></span>
                <input class="form-control" type="text" size="4" data-stripe="cvc" value="" />
              </label>
            </div>

            <div class="form-row">
              <label>
                <span>Customer Email</span>
                <input type="text" size="50" name="email" value="" />
              </label>
            </div>
            <div class="form-row">
              <label>
                <span>Notes</span>
                <input type="text" size="50" name="notes" value="" />
              </label>
            </div>

            <button type="submit">Submit Payment</button>
          </form>
          <div id="overlay">
            <div id="scanning" class="dialog">
              <p>Scanning...</p>
            </div>
          </div>

          <div id="failure" class="dialog">
            <p>Unrecognized card.</p>
          </div>

          <div id="success" class="dialog">
            <p>Successful scan!</p>

            <div id="properties">

            </div>
          </div>
          <script type="text/javascript" src="<?=$us_url_root?>usersc/plugins/stripe/assets/swipe.js"></script>
          <script type="text/javascript">

          // Called by plugin on a successful scan.
          var complete = function (data) {

            // Is it a payment card?
            if (data.type == "generic")
            return;

            // Copy data fields to form
            $("#firstName").val(data.firstName);
            $("#lastName").val(data.lastName);
            $("#account").val(data.account);
            $("#expMonth").val(data.expMonth);
            $("#expYear").val(data.expYear);
            $("#type").val(data.type);

          };

          // Event handler for scanstart.cardswipe.
          var scanstart = function () {
            $("#overlay").fadeIn(50);
          };

          // Event handler for scanend.cardswipe.
          var scanend = function () {
            $("#overlay").fadeOut(50);
          };

          // Event handler for success.cardswipe.  Displays returned data in a dialog
          var success = function (event, data) {

            $("#properties").empty();

            // Iterate properties of parsed data
            for (var key in data) {
              if (data.hasOwnProperty(key)) {
                var text = key + ': ' + data[key];
                $("#properties").append('<div class="property">' + text + '</div>');
              }
            }


            $("#success").fadeIn().delay(500).fadeOut();
          }

          var failure = function () {
            $("#failure").fadeIn().delay(1500).fadeOut();
          }

          // Initialize the plugin with default parser and callbacks.
          //
          // Set debug to true to watch the characters get captured and the state machine transitions
          // in the javascript console. This requires a browser that supports the console.log function.
          //
          // Set firstLineOnly to true to invoke the parser after scanning the first line. This will speed up the
          // time from the start of the scan to invoking your success callback.
          $.cardswipe({
            firstLineOnly: true,
            success: complete,
            parsers: ["visa", "amex", "mastercard", "discover", "generic"],
            debug: false
          });

          // Bind event listeners to the document
          $(document)
          .on("scanstart.cardswipe", scanstart)
          .on("scanend.cardswipe", scanend)
          .on("success.cardswipe", success)
          .on("failure.cardswipe", failure)
          ;
        </script>

        <!-- Content Ends Here -->
      </div> <!-- /.col -->
    </div> <!-- /.row -->
  </div> <!-- /.container -->
</div> <!-- /.wrapper -->


<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>
<script>
// PART 1 - Client Side
// Create the card token using Stripe.js
// set Stripe publishable key: remember to change this to your live secret key in production
// See your keys here https://dashboard.stripe.com/account/apikeys
Stripe.setPublishableKey("<?=$settings->stripe_public?>");
// grab payment form
var paymentForm = document.getElementById("payment-form");
// listen for submit
paymentForm.addEventListener("submit", processForm, false);
/* Methods */
// process form on submit
function processForm(evt) {
  // prevent form submission
  evt.preventDefault();
  // create stripe token
  Stripe.card.createToken(paymentForm, stripeResponseHandler);
};
// handle response back from Stripe
function stripeResponseHandler(status, response) {
  // if an error
  if (response.error) {
    // respond in some way
    alert("Error: " + response.error.message);
  }
  // if everything is alright
  else {
    // creates a token input element and add that to the payment form
    var token = document.createElement("input");
    token.name = "stripeToken";
    token.value = response.id; // token value from Stripe.card.createToken
    token.type = "hidden"
    paymentForm.appendChild(token);
    // resubmit form
    //alert("Form will submit!\n\nToken ID = " + response.id);
    // uncomment below to actually submit
    paymentForm.submit();
  }
};
</script>


<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html ?>
