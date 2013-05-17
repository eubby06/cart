<?php namespace Eubby06\Cart;


use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

/**
 * Laravel 4 Shopping Cart Class
 *
 * @vendor		Eubby
 * @package		Cart
 * @author		Yonanne Remedio
 */
class Cart {

	// These are the regular expression rules that we use to validate the product ID and product name
	protected $productIdRules	= '\.a-z0-9_-'; // alpha-numeric, dashes, underscores, or periods
	protected $productNameRules	= '\.\:\-_ a-z0-9'; // alpha-numeric, dashes, underscores, colons or periods

	// Private variables.
	protected $_cartContents = array();

	/**
	 * Shopping Class Constructor
	 *
	 * Sets passed configs
	 */
	public function __construct($params = array())
	{
		$config = array();

		if(count($params) > 0)
		{
			foreach($params as $key => $val)
			{
				$config[$key] = $val;
			}
		}

		//get data and set to cart contents variable, only if it has been set
		if(Session::has('cartContents'))
		{
			$this->_cartContents = Session::get('cartContents');
		}
		else
		{
			// No cart exists so we'll set some base values
			$this->_cartContents['cart_total'] = 0;
			$this->_cartContents['total_items'] = 0;
			$this->_cartContents['discount_amount'] = 0;
			$this->_cartContents['discounted_total'] = 0;
			$this->_cartContents['discount_code'] = '';
		}

		Log::info('Cart Class Initialized.');
	}

	/**
	 * Insert items into the cart and save it to the session table
	 *
	 * @access	public
	 * @param	array
	 * @return	bool
	 */
	public function insert($items = array())
	{
		if(!is_array($items) OR count($items) == 0)
		{
			Log::error('The insert method must be passed an array containing data.');
			return FALSE;
		}

		$saveCart = FALSE;

		if(isset($items['id']))
		{
			if(($rowid = $this->_insert($items)))
			{
				$saveCart = TRUE;
			}
		}
		else
		{
			foreach($items as $item)
			{
				if(is_array($item) AND isset($item['id']))
				{
					if($this->_insert($item))
					{
						$saveCart = TRUE;
					}
				}
			}
		}

		if($saveCart == TRUE)
		{
			$this->_saveCart();
			return isset($rowid) ? $rowid : TRUE;
		}
		
		return FALSE;
	}

	/**
	 * Insert
	 *
	 * @access	private
	 * @param	array
	 * @return	bool
	 */
	protected function _insert($item = array())
	{
		if(!is_array($item) OR count($item) == 0)
		{
			Log::error('The insert method must be passed an array containing data.');
			return FALSE;
		}

		if(!isset($item['id']) OR !isset($item['qty']) OR !isset($item['price']) OR !isset($item['name']))
		{
			Log::error('The cart array must contain a product ID, quantity, price, and name.');
			return FALSE;
		}

		// Prep the quantity. It can only be a number.  Duh...
		$item['qty'] = trim(preg_replace('/([^0-9])/i', '', $item['qty']));
		// Trim any leading zeros
		$item['qty'] = trim(preg_replace('/(^[0]+)/i', '', $item['qty']));

		// If the quantity is zero or blank there's nothing for us to do
		if ( ! is_numeric($item['qty']) OR $item['qty'] == 0)
		{
			return FALSE;
		}

		if ( ! preg_match("/^[".$this->productIdRules."]+$/i", $item['id']))
		{
			Log::error('Invalid product ID.  The product ID can only contain alpha-numeric characters, dashes, and underscores');
			return FALSE;
		}

		if ( ! preg_match("/^[".$this->productNameRules."]+$/i", $item['name']))
		{
			Log::error('An invalid name was submitted as the product name: '.$items['name'].' The name can only contain alpha-numeric characters, dashes, underscores, colons, and spaces');
			return FALSE;
		}

		$item['price'] = trim(preg_replace('/([^0-9\.])/i', '', $item['price']));
		$item['price'] = trim(preg_replace('/(^[0]+)/i', '', $item['price']));

		if ( ! is_numeric($item['price']))
		{
			return FALSE;
		}

		if (isset($item['options']) AND count($item['options']) > 0)
		{
			$rowid = md5($item['id'].implode('', $item['options']));
		}
		else
		{
			$rowid = md5($item['id']);
		}

		// --------------------------------------------------------------------

		// Now that we have our unique "row ID", we'll add our cart items to the master array

		// let's unset this first, just to make sure our index contains only the data from this submission
		unset($this->_cartContents[$rowid]);

		// Create a new index with our new row ID
		$this->_cartContents[$rowid]['rowid'] = $rowid;

		// And add the new items to the cart array
		foreach ($item as $key => $val)
		{
			$this->_cartContents[$rowid][$key] = $val;
		}

		// Woot!
		return $rowid;
	}

	/**
	 * Save the cart array to the session DB
	 *
	 * @access	private
	 * @return	bool
	 */
	protected function _saveCart()
	{
		// Unset these so our total can be calculated correctly below
		unset($this->_cartContents['cart_total']);
		unset($this->_cartContents['total_items']);
		unset($this->_cartContents['discounted_total']);

		// Lets add up the individual prices and set the cart sub-total
		$total = 0;
		$items = 0;
		foreach ($this->_cartContents as $key => $val)
		{
			// We make sure the array contains the proper indexes
			if ( ! is_array($val) OR ! isset($val['price']) OR ! isset($val['qty']))
			{
				continue;
			}

			$total += ($val['price'] * $val['qty']);
			$items += $val['qty'];

			// Set the subtotal
			$this->_cartContents[$key]['subtotal'] = ($this->_cartContents[$key]['price'] * $this->_cartContents[$key]['qty']);
		}

		// Set the cart total and total items.
		$this->_cartContents['total_items'] = $items;
		$this->_cartContents['cart_total'] = $total;
		$this->_cartContents['discounted_total'] = $total - $this->_cartContents['discount_amount'];

		// Is our cart empty?  If so we delete it from the session
		if (count($this->_cartContents) <= 2)
		{
			Session::forget('cartContents');

			// Nothing more to do... coffee time!
			return FALSE;
		}

		// If we made it this far it means that our cart has data.
		// Let's pass it to the Session class so it can be stored
		Session::put('cartContents', $this->_cartContents);

		// Woot!
		return TRUE;
	}

	/**
	 * Update the cart
	 *
	 * This function permits the quantity of a given item to be changed.
	 * Typically it is called from the "view cart" page if a user makes
	 * changes to the quantity before checkout. That array must contain the
	 * product ID and quantity for each item.
	 *
	 * @access	public
	 * @param	array
	 * @param	string
	 * @return	bool
	 */
	public function update($items = array())
	{
		if(!is_array($items) OR count($items) == 0)
		{
			return FALSE;
		}

		$saveCart = FALSE;
		if(isset($items['rowid']) AND isset($items['qty']))
		{
			if($this->_update($items) == TRUE)
			{
				$saveCart = TRUE;
			}
		}
		else
		{
			foreach($items as $item)
			{
				if(is_array($item) AND isset($item['rowid']) AND isset($item['qty']))
				{
					if($this->_update($item) == TRUE)
					{
						$saveCart = TRUE;
					}
				}
			}
		}

		if($saveCart == TRUE)
		{
			$this->_saveCart();
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Update the cart
	 *
	 * This function permits the quantity of a given item to be changed.
	 * Typically it is called from the "view cart" page if a user makes
	 * changes to the quantity before checkout. That array must contain the
	 * product ID and quantity for each item.
	 *
	 * @access	private
	 * @param	array
	 * @return	bool
	 */
	protected function _update($item = array())
	{
		if(!isset($item['qty']) OR !isset($item['rowid']) OR !isset($this->_cartContents['rowid']))
		{
			return FALSE;
		}

		$item['qty'] = preg_replace('/([^0-9])/i', '', $item['qty']);

		if(!is_numeric($item['qty']))
		{
			return FALSE;
		}

		if($this->_cartContents[$item['rowid']]['qty'] == $item['qty'])
		{
			return FALSE;
		}

		if($items['qty'] == 0)
		{
			unset($this->_cartContents[$item['rowid']]);
		}
		else
		{
			$this->_cartContents[$item['rowid']]['qty'] = $item['qty'];
		}

		return TRUE;
	}

	/**
	 * Cart Contents
	 *
	 * Returns the entire cart array
	 *
	 * @access	public
	 * @return	array
	 */
	public function contents()
	{
		$cart = $this->_cartContents;

		return $cart;
	}

	/**
	 * Cart Total
	 *
	 * @access	public
	 * @return	integer
	 */
	public function total()
	{
		return $this->_cartContents['cart_total'];
	}

	/**
	 * Total Items
	 *
	 * Returns the total item count
	 *
	 * @access	public
	 * @return	integer
	 */
	public function totalItems()
	{
		return $this->_cartContents['total_items'];
	}

	public function hasOptions($rowid = '')
	{
		if(!isset($this->_cartContents[$rowid]['options']) OR count($this->_cartContents[$rowid]['options']) === 0)
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Product options
	 *
	 * Returns the an array of options, for a particular product row ID
	 *
	 * @access	public
	 * @return	array
	 */
	protected function productOptions($rowid = '')
	{
		if(!isset($this->cartContents[$rowid]['options']))
		{
			return array();
		}

		return $this->_cartContents[$rowid]['options'];
	}

	/**
	 * Format Number
	 *
	 * Returns the supplied number with commas and a decimal point.
	 *
	 * @access	public
	 * @return	integer
	 */
	function formatNumber($n = '')
	{
		if ($n == '')
		{
			return '';
		}

		// Remove anything that isn't a number or decimal point.
		$n = trim(preg_replace('/([^0-9\.])/i', '', $n));

		return number_format($n, 2, '.', ',');
	}

	/**
	 * Destroy the cart
	 *
	 * Empties the cart and kills the session
	 *
	 * @access	public
	 * @return	null
	 */
	public function destroy()
	{
		unset($this->_cartContents);

		$this->_cartContents['cart_total'] = 0;
		$this->_cartContents['total_items'] = 0;
		$this->_cartContents['discount_amount'] = 0;
		$this->_cartContents['discounted_total'] = 0;
		$this->_cartContents['discount_code'] = '';

		Session::forget('cartContents');
	}

	public function applyDiscount($params = array())
	{
		if(!isset($params['value']) OR !isset($params['type']) OR !isset($params['code']))
		{
			return FALSE;
		}

		$this->_setDiscount($params);
		return TRUE;
	}

	protected function _setDiscount($params = array())
	{
		$amount = $params['value'];

		unset($this->_cartContents['discount']);

		if($params['type'] == 'percentage')
		{
			$amount = ($params['value'] / 100) * $this->total();
		}

		$this->_cartContents['discount_amount'] = $amount;
		$this->_cartContents['discount_code'] = $params['code'];

		$this->_saveCart();
		return TRUE;
	}

	public function discount()
	{
		return $this->_cartContents['discount'];
	}

	public function discountCode()
	{
		return $this->_cartContents['discount_code'];
	}
}