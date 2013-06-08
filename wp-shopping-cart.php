<?php

/* ======================================================================

    WordPress Shopping Cart v1.0
    A simple PayPal shopping cart for WordPress by Chris Ferdinandi.
    http://gomakethings.com

    Free to use under the MIT License.
    http://gomakethings.com/mit/


    HOW TO USE

    1. Replace the variables with your information:
        * $paypal_account - Your PayPal email address
        * $cart_icon - An icon for your shopping cart link, if you have one
        * $url_store - The URL of your store
        * $url_cart - The URL of your shopping cart
        * $url_update - The meta info/URL when the cart is updated
        * $url_success - The meta info/URL when the checkout is successful
        * Shipping variables - Based on shopping cart subtotal value

    2. Include an "Add to Cart" link using the [add_to_cart] shortcode.
       Shortcode variables:
        * product="PRODUCT NAME"
        * price="X.XX"
        * options="TITLE=OPTION 1|OPTION 2|OPTION 3" (Not required. Limit 1 set of options.)
        * sold="SOLD OUT MESSAGE"

       Example: [add_to_cart product="T-Shirt" price="20" options="Size=Small|Med|Large"]

    3. Add a Shopping Cart using the [checkout_cart] shortcode 
       or <?php echo checkout_cart(); ?>.

    4. Add a link to the shopping cart using the [checkout_cart_link] shortcode 
       or <?php echo checkout_cart_link(); ?>.
    
 * ====================================================================== */


/* =============================================================
    VARIABLES
    Updated as needed.
 * ============================================================= */

// PayPal Account
// Replace with your PayPal email address
$paypal_account = '';

// Define image directory (change as needed)
$img_directory = get_template_directory_uri() . '/img/';

// If you want an icon next to the shopping cart link, add it here
// ex. $cart_icon = '<img src="' . $img_directory . 'cart-icon.png ">';
// ex. $cart_icon = '<i class="icon-cart"></i> ';
// Learn more about icon fonts: http://gomakethings.com/icon-fonts/
$cart_icon = '';

// Get current page URL and clean it up
$url_current  = @( $_SERVER["HTTPS"] != 'on' ) ? 'http://'.$_SERVER["SERVER_NAME"] :  'https://'.$_SERVER["SERVER_NAME"];
$url_current .= ( $_SERVER["SERVER_PORT"] !== 80 ) ? ":".$_SERVER["SERVER_PORT"] : "";
$url_current .= $_SERVER["REQUEST_URI"];
$url_clean = array_shift( explode('?', $url_current) );

// Define the Store URL (change as needed)
$url_store = get_option('home') . '/store/';

// Define Shopping Cart URL (change as needed)
$url_cart = get_option('home') . '/cart/';

// Define "Cart Updated" URL (change as needed)
$url_update = $url_cart . '?cart-updated';

// Define "Checkout Success" URL (change as needed)
$url_success = $url_cart . '?checkout-success';

// Shipping Costs
function checkout_cart_shipping() {

    // Shopping cart variables
    $checkout_cart_subtotal = 0;

    // If shopping cart session exists...
    if ( isset($_SESSION['shopping_cart']) ) {
    
        // For each item in shopping cart...
	    foreach($_SESSION['shopping_cart'] as $item) {

	        // Define shopping cart variables
            $item_price = $item['product_price'];
            $item_quantity = $item['product_quantity'];
            $item_total_cost = $item_price * $item_quantity;
            $checkout_cart_subtotal += $item_total_cost;

        }

    }

    // Shipping Variables
    // Adjust as needed for your project

    // Base shipping rate
    $checkout_cart_shipping = 5;

    // For orders of $20 and more
    if ($checkout_cart_subtotal >= 20) {
        // Shipping = $10
        $checkout_cart_shipping = 10;
    }

    // For orders of $50 and more
    if ($checkout_cart_subtotal >= 50) {
        // Shipping = $20
        $checkout_cart_shipping = 20;
    }

    // For orders of $100 and more
    if ($checkout_cart_subtotal >= 100) {
        // Shipping = $20
        $checkout_cart_shipping = 50;
    }


    // Display the shipping value
    return $checkout_cart_shipping;
}



/* =============================================================
    FUNCTIONS
    Creates the shopping cart and add-to-cart buttons.
    Processes cart actions.
 * ============================================================= */

// Start browsing session (for data storage)
if(!isset($_SESSION)) {
	session_start();
}



// "Add to Cart" Button
function add_to_cart($atts) {

    // Set and get product variables
    extract(shortcode_atts(array(  
        'label' => 'Add to Cart',
        'product' => '',
        'price' => 0,
        'options' => '',
        'sold' => ''
    ), $atts));

    // If options exist...
    $option_form = '';
    if ( $options != '' ) {

        // Options Variables
        $option_title = array_shift(explode('=', $options));
        $option_variables = strstr( $options, '=' );
        $option_variables = str_replace( '=', '', $option_variables);
        $option_variables = explode( '|', $option_variables);

        // Create form element for each product option
        $option_form_items = '';
        foreach ($option_variables as $variable) {
            $option_form_items .= '<option value="' . $variable . '">' . $variable . '</option>';
        }

        // Drop options into a form select element
        $option_form = '
            <legend>' . $option_title . '</legend>
            <p class="no-space">
                <select name="product_option" class="input-inline">
                    ' . $option_form_items . '
                </select>
            </p>
        ';
    }

    // "Add to Cart" Form/Button
    if ( $sold == '' ) {
        $add_to_cart = '
            <form action="" method="post">
                <input type="hidden" name="product_id" value="' . $product . '">
                <input type="hidden" name="product_price" value="' . $price . '">
                <input type="hidden" name="product_url" value="' . $url_current . '">
                ' . $option_form . '
                <input type="hidden" name="add-to-cart" value="add-to-cart">
                <p><button type="submit" class="btn">' . $label . '</button></p>
            </form>
        ';
    }

    // If item is sold out, disable "Add to Cart" button
    else {
        $add_to_cart = '<p><strong>' . $sold . '</strong></p>';
    }

    // Show "Add to Cart" Button
    return $add_to_cart;
    
}
add_shortcode("add_to_cart", "add_to_cart");



// "Add to Cart" Action
if ($_POST['add-to-cart'] ) {

    // Set product variables
    $prod_name = $_POST['product_id'];
    $prod_price = $_POST['product_price'];
    $prod_option = $_POST['product_option'];
    $prod_url = $url_current;

    // If product options exist
    if ($prod_option != '') {
        // Append selected options to product name
        $prod_name = $prod_name . ' ' . $prod_option;
    }

    // Cleanup product name and set as ID
    $prod_name = stripslashes($prod_name);
    $prod_name_cleanup = array('&' => 'and', '%' => 'Percent');
    $prod_name = strtr($prod_name, $prod_name_cleanup);
    $prod_id_cleanup = array(' ' => '_', '\'' => '', '"' => '');
    $prod_id = strtr($prod_name, $prod_id_cleanup);

    // If shopping cart session doesn't exist, create one
    if ( !isset($_SESSION['shopping_cart']) ) {
        $_SESSION['shopping_cart'] = array();
    }

    // If product isn't stored in session, add it
    if ( !isset($_SESSION['shopping_cart'][$prod_id]) ) {
        $_SESSION['shopping_cart'][$prod_id] = array(
            'product_id' => $prod_id,
            'product_name' => $prod_name,
            'product_price' => $prod_price,
            'product_quantity' => 1,
            'product_url' => $url_clean
        );
    }

    // If product is already stored in session, update quantity by one
    else {
        $_SESSION['shopping_cart'][$prod_id]['product_quantity'] = $_SESSION['shopping_cart'][$prod_id]['product_quantity']+1;
    }

    // Redirect page to prevent duplicate "add to cart" on page refresh
    header('Location:' . $url_update);
    exit();
     
}



// Checkout Cart
function checkout_cart() {  

    // Checkout Cart Variables
    $checkout_cart = '';
    $checkout_cart_count = 0;
    $checkout_cart_subtotal = 0;
    $checkout_cart_shipping = checkout_cart_shipping();
    global $paypal_account, $url_current, $url_store, $url_success, $img_directory;

    // If shopping cart session exists...
    if ( isset($_SESSION['shopping_cart']) ) {
    
        // For each item in shopping cart...
	    foreach($_SESSION['shopping_cart'] as $item) {

	        // Define shopping cart variables
            $item_id = $item['product_id'];
            $item_name = $item['product_name'];
            $item_price = $item['product_price'];
            $item_quantity = $item['product_quantity'];
            $item_url = $item['product_url'];
            $item_total_cost = $item_price * $item_quantity;
            $checkout_cart_subtotal += $item_total_cost;

            // Remove from Cart Button
            $remove_from_cart = '<a href="?action=remove-from-cart&id=' . $item_id . '">remove</a>';

            // Item Quantity Form Element
            $item_quantity_form = '<input type="text" name="' . $item_id . '" value="' . $item_quantity . '" class="input-condensed text-center input-inline" style="width: 4em;">';

            // Add quantity to count of items in cart
            $checkout_cart_count += $item_quantity;

            // Create table row for product in cart
            $checkout_cart_content .= '
                <tr>
                    <td class="text-left">
                        <a href="' . $item_url . '">' . $item_name . '</a><br>
                        <span class="text-muted text-small">$' . $item_price . '</span>
                    </td>
                    <td style="width: 4em;">' . $item_quantity_form . '</td>
                    <td>$' . $item_total_cost . '</td>
                    <td>' . $remove_from_cart . '</td>
                </tr>
            ';

            // Create table row for product for purchase summary
            $checkout_cart_complete .= '
                <tr>
                    <td class="text-left">
                        <a href="' . $item_url . '">' . $item_name . '</a><br>
                        <span class="text-muted text-small">$' . $item_price . '</span>
                    </td>
                    <td style="width: 4em;">' . $item_quantity . '</td>
                    <td>$' . $item_total_cost . '</td>
                </tr>
            ';
	    }

        // Cart total variable
        $checkout_cart_total = $checkout_cart_subtotal + $checkout_cart_shipping;

        // Checkout cart form
        $checkout_cart = '
            <form action="" method="post">
                <table class="table text-center">
                    <thead>
                        <tr>
                            <th class="text-left">Item</th>
                            <th style="width: 4em;">#</th>
                            <th>Price</th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>' . $checkout_cart_content . '
                    </tbody>
                </table>

                <p class="no-space-bottom text-right">Subtotal: $' . $checkout_cart_subtotal . '</p>
                <p class="space-bottom-small text-right">Shipping: $' . $checkout_cart_shipping . '</p>
                <p class="text-tall text-right">Total: $' . $checkout_cart_total . '</p>
                <p class="text-right">
                    <button type="submit" name="submit" value="update-cart" class="btn btn-muted">Update</button>
                    <button type="submit" name="submit" value="cart-checkout" class="btn">Checkout</button>
                </p>
            </form>
            <p class="text-right"><img src="' . $img_directory . 'paypal.jpg" height="53" width="200" alt="Secure payments by PayPal. Pay with Visa, Mastercard, Discover or American Express."></p>
        ';

    }

    // If checkout complete
    if ( $url_current == $url_success && isset($_SESSION['shopping_cart']) ) {

        // Success message and purchase summary
        $checkout_cart = '
            <p>Thanks for your purchase! You should receive an invoice confirmation by mail. If you have any questions, please contact <a href="mailto:' . $paypal_account . '">' . $paypal_account . '</a>.</p>
            <h3>Order Summary</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="width: 4em;">#</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>' . $checkout_cart_complete . '
                </tbody>
            </table>

            <p class="no-space-bottom text-right">Subtotal: $' . $checkout_cart_subtotal . '</p>
            <p class="space-bottom-small text-right">Shipping: $' . $checkout_cart_shipping . '</p>
            <p class="text-tall text-right">Total: $' . $checkout_cart_total . '</p>
        ';

        // Empty cart
        unset($_SESSION['shopping_cart']);
        
    }

    // If cart is empty
    elseif ( $checkout_cart_count == 0 ) {
        $checkout_cart = '<p>Your shopping cart is empty. <a href="' . $url_store . '">Visit the store.</a></p>';
    }

   // Display checkout cart
    return $checkout_cart;
    
}
add_shortcode("checkout_cart", "checkout_cart");



// "Remove from Cart" Action
if (stripos($url_current, '?action=remove-from-cart') !== false) {

    // If product ID exists in shopping cart...
    if ( isset($_GET['id']) ) {

        // Define product variable
        $prod_id = $_GET['id'];

        // Remove product from shopping cart
        unset($_SESSION['shopping_cart'][$prod_id]);

    }

    // Redirect page to prevent duplicate "remove from cart" on refresh
    header('Location:' . $url_update);
    exit();

}



// "Update Cart" Action
if ($_POST['submit'] === 'update-cart' ) {

    // For each item in the cart...
    foreach($_SESSION['shopping_cart'] as $item) {

        // Set shopping cart variables
        $item_id = $item['product_id'];
        $item_quantity = $item['product_quantity'];

        $new_item_quantity = $_POST[$item_id];

        // If quantity is set to at least one item...
        if ( $new_item_quantity > 0 ) {
            // Update product quantity
            $_SESSION['shopping_cart'][$item_id]['product_quantity'] = $new_item_quantity;
        }

        // If quantity is set to zero...
        else {
            // Remove item from shopping cart
            unset($_SESSION['shopping_cart'][$item_id]);
        }

    }

    // Redirect page to prevent form resubmission on page refresh
    header('Location:' . $url_update);
    exit();

}



// "Checkout" Action
if ($_POST['submit'] === 'cart-checkout' ) {  

    // PayPal Variables
    $paypal_items = '';
    $count = 0;
    $checkout_cart_shipping = checkout_cart_shipping();

    // For each item in shopping cart...
    foreach($_SESSION['shopping_cart'] as $item) {

        // Define shopping cart variables
        $item_name = $item['product_name'];
        $item_price = $item['product_price'];
        $item_quantity = $item['product_quantity'];
        $item_total_cost = $item_price * $item_quantity;
        $checkout_cart_subtotal += $item_total_cost;
        $count = $count+1;

        // Define PayPal variables
        $paypal_item_name = '&item_name_' . $count . '=' . $item_name;
        $paypal_item_price = '&amount_' . $count . '=' . $item_price;
        $paypal_item_quantity = '&quantity_' . $count . '=' . $item_quantity;
        $paypal_item_shipping = '&shipping_' . $count . '=0';

        // Paypal Data for each item
        $paypal_items .= $paypal_item_name . $paypal_item_price . $paypal_item_quantity . $paypal_item_shipping;

    }
    
    // Paypal Checkout URL
    $paypal_data = 'https://www.paypal.com/cgi-bin/webscr?cmd=_cart&business=' . $paypal_account . $paypal_items . '&shipping_1=' . $checkout_cart_shipping . '&upload=1&no_note=1&rm=1&no_shipping=2&return=' . $url_success . '&cancel_return=' . $url_cart;

    // Redirect to PayPal for checkout
    header('Location: ' . $paypal_data);
    exit();

}



// Link To Shopping Cart
function checkout_cart_link() {

    // Variables
    $checkout_cart_count = 0;
    global $cart_icon, $url_current, $url_cart, $url_success;
    

    // If shopping cart session exists...
    if ( isset($_SESSION['shopping_cart']) ) {
        // For each item...
        foreach($_SESSION['shopping_cart'] as $item) {
            $item_quantity = $item['product_quantity'];
            // Add item quantity to count
            $checkout_cart_count += $item_quantity;
        }
    }

    // If checkout is complete, set items in cart to zero
    if ( $url_current == $url_success ) {
        $checkout_cart_count = 0;
    }

    // Create a link to the shopping cart, with number of items in cart
    $checkout_cart_link = '<a href="' . $url_cart . '">' . $cart_icon . 'Cart (' . $checkout_cart_count . ')</a>';

    // Display link to the shopping cart
    return $checkout_cart_link;
}
add_shortcode("checkout_cart_link", "checkout_cart_link");

?>
