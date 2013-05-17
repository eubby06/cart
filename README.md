cart
====

Shopping Cart Package based on Codeigniter's Cart Class

##INSTALLATION

    "require": {
    	"laravel/framework": "4.0.*",
	"eubby06/cart": "dev-master"
	}

	composer update

add to providers:

	'providers' => array(
	                    'Eubby06\Cart\CartServiceProvider'

add to aliases:

	'aliases' => array(
	                'Cart' => 'Eubby06\Cart\Facades\Cart'


##USAGE

```php
Add Item:

//create an array variable
$data = array(
               'id'      => 'sku_123ABC',
               'qty'     => 1,
               'price'   => 39.95,
               'name'    => 'T-Shirt',
               'options' => array('Size' => 'L', 'Color' => 'Red')
            );

//call Cart method insert and pass the array data
Cart::insert($data); 


Update Qty:

//create an array variable
$data = array(
               'rowid' => 'n45cm8cdf16sdf3sdfsdfs0b6gde8gd',
               'qty'   => 3
            );

Cart::update($data); 


Apply Discount:

//create an array variable
$discount = array(
               'value'      => '10',
               'type'     => 'percentage',
               'code'   => 'urdiscountcode'
            );

//call Cart method insert and pass the array data
Cart::applyDiscount($discount); 


//returns cart total amount
Cart::total();


//returns total number of items in the cart.
Cart::totalItems();


//returns all items in the cart.
Cart::contents();


//return boolean
Cart::hasOptions(rowid);


//returns item options
Cart::productOptions(rowid);


//destroy cart session
Cart::destroy();

```
