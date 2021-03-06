<?php

use Laravel\HTML;

class Menu {

	/**
	 * All the menu containers
	 *
	 * @var array
	 */
	public static $containers = array();

	/**
	 * Create a new MenuItems array
	 * 
	 * @return MenuItems
	 */
	public static function items()
	{
		return new MenuItems();
	}

	/**
	 * Get a MenuHandler.
	 *
	 * <code>
	 *		// Get the menu handler that handles the default container
	 *		$handler = Menu::handler();
	 *
	 *		// Get a named menu handler for a single container
	 *		$handler = Menu::handler('backend');
	 * 
	 *		// Get a menu handler that handles multiple containers
	 *		$handler = Menu::handler(array('admin', 'sales'));
	 * </code>
	 *
	 * @param  string            $container
	 * @return Menu
	 */
	public static function handler($containers = '')
	{
		$containers = (array) $containers;

		// Create a new MenuItems instance for the containers that don't exist yet
		foreach ($containers as $container)
		{
			if( ! array_key_exists($container, static::$containers))
			{
				static::$containers[$container] = new MenuItems;
			}
		}
		
		// Return a Handler for the given containers
		return new MenuHandler($containers);
	}

	/**
	 * Magic Method for calling methods on the default handler.
	 *
	 * <code>
	 *		// Call the "render" method on the default handler
	 *		echo Menu::render();
	 *
	 *		// Call the "add" method on the default handler
	 *		Menu::add('home', 'Home');
	 * </code>
	 */
	public static function __callStatic($method, $parameters)
	{
		return call_user_func_array(array(static::handler(), $method), $parameters);
	}

}

class MenuHandler {

	public $handles = array();

	/**
	 * Prefix the links with the container name or a custom string
	 * 
	 * @var mixed
	 */
	public $prefix = '';

	/**
	 * Set the container(s) where this handler should act upon
	 * 
	 * @param array $containers The containers to forward calls to
	 */
	public function __construct($containers)
	{
		$this->handles = $containers;
	}

	/**
	 * Magic method that will pass the incoming calls to all of the containers this handler handles
	 * 
	 * @param  string $method
	 * @param  array $parameters
	 * @return MenuHandler
	 */
	public function __call($method, $parameters)
	{
		// Loop through the containers this handler handles
		foreach($this->handles as $handle)
		{
			// Pass the call to the container
			$menuitems = Menu::$containers[$handle];
			Menu::$containers[$handle] = call_user_func_array(array($menuitems, $method), $parameters);
		}

		return $this;
	}

	/**
	 * Prefix links with a custom string
	 * 
 	 * @return MenuHandler
	 */
	public function prefix($prefix = '')
	{
		$this->prefix = $prefix.'/';

		return $this;
	}

	/**
	 * Prefix links with the name of the container
	 * 
	 * @return MenuHandler
	 */
	public function prefix_container()
	{
		$this->prefix = true;

		return $this;
	}

	/**
	 * Get the evaluated string content for the menu containers this menuhandler acts upon.
	 *
	 * @return string
	 */
	public function render($attributes = array(), $element = 'ul')
	{
		$html = '';
		foreach($this->handles as $handle)
		{
			$html .= $this->render_items(Menu::$containers[$handle]->items, $attributes, $element);
		}

		return $html;
	}

	/**
	 * Get the evaluated string content of the view.
	 * 
	 * @param  MenuItems 	$menuitems         	The menu items to render
	 * @param  array  		$attributes 		Attributes for the element
	 * @param  string  		$element 			The type of the element (ul or ol)
	 * @return string
	 */
	public function render_items($menuitems, $attributes = array(), $element = 'ul')
	{
		if(is_null($menuitems)) return '';

		$items = array();
		foreach($menuitems as $menuitem)
		{
			if( ! array_key_exists('html', $menuitem))
			{
				$menuitem['url'] = (gettype($this->prefix) == 'string' ? $this->prefix : $this->container) . $menuitem['url'];

				if($this->is_active($menuitem))
				{
					$menuitem['list_attributes'] = merge_attributes($menuitem['list_attributes'], array('class' => 'active'));
				}

				if($this->has_active_children($menuitem))
				{
					$menuitem['list_attributes'] = merge_attributes($menuitem['list_attributes'], array('class' => 'active-children'));
				}
			}
			
			$menuitem['children'] = isset($menuitem['children']->items) ? $this->render_items($menuitem['children']->items, $attributes, $element) : '';

			$items[] = $this->render_item($menuitem);
		}
		
		return MenuHTML::$element($items, $attributes);
	}

	public function is_active($menuitem)
	{
		if($menuitem['url'] == URI::current())
		{
			return true;
		}

		return false;
	}

	public function has_active_children($menuitem)
	{
		if( ! isset($menuitem['children']->items))
		{
			return false;
		}

		foreach ($menuitem['children']->items as $child)
		{
			if($this->is_active($child))
			{
				return true;
			}

			if(isset($child['children']->items))
			{
				return $this->has_active_children($child);
			}
		}
	}

	/**
	 * Turn item data into HTML
	 * 
	 * @param 	array 	$item 		The menu item
	 * @return 	string 	The HTML
	 */
	protected function render_item($item)
	{
		extract($item);

		if(array_key_exists('html', $item))
		{
			return MenuHTML::$list_element($html.PHP_EOL.$children, $list_attributes);
		}
		else
		{
			return MenuHTML::$list_element(MenuHTML::link($url, $title, $link_attributes).PHP_EOL.$children, $list_attributes);
		}
	}

	/**
	 * Get the evaluated string content for the menu containers this menuhandler acts upon.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->render();
	}

}

class MenuItems {

	/**
	 * The menu items
	 * 
	 * @var array
	 */
	public $items = array();
	
	/**
	 * Create a new MenuItems instance
	 */
	public static function factory()
	{
		return new static;
	}
	
	/**
	 * Add a menu item to the MenuItems instance.
	 *
	 * <code>
	 *		// Add a item to the default main menu
	 *		Menu::add('home', 'Homepage');
	 *
	 *		// Add a subitem to the homepage
	 *		Menu::add('home', 'Homepage', Menu::items()->add('home/sub', 'Subitem'));
	 *
	 *		// Add a item that has attributes applied to its tag
	 *		Menu::add('home', 'Homepage', null, array('class' => 'fancy'));
	 * </code>
	 *
	 * @param  string  $url
	 * @param  string  $title
	 * @param  array   $attributes
	 * @param  array   $children
	 * @return MenuItems
	 */
	public function add($url, $title, $children = null, $link_attributes = array(), $list_attributes = array(), $list_element = 'li')
	{
		$this->items[] = compact('url', 'title', 'children', 'link_attributes', 'list_attributes', 'list_element');

		return $this;
	}

	/**
	 * Add a raw html item to the MenuItems instance.
	 *
	 * <code>
	 *		// Add a raw item to the default main menu
	 *		Menu::raw('<img src="img/seperator.gif">');
	 * </code>
	 *
	 * @param  string  $url
	 * @param  string  $title
	 * @param  array   $attributes
	 * @param  array   $children
	 * @return MenuItems
	 */
	public function raw($html, $list_attributes = array(), $list_element = 'li')
	{
		$this->items[] = compact('html', 'list_attributes', 'list_element');
		
		return $this;
	}

	/**
	 * Add menu items to another MenuItems instance.
	 *
	 * <code>
	 * 		// Attach menu items to the default menu handler
	 *		Menu::attach(Menu::items()->add('home', 'Homepage'));
	 * </code>
	 *
	 * @param  MenuItems  $menuitems
	 * @return Void
	 */	
	public function attach($menuitems)
	{
		$this->items = array_merge($this->items, $menuitems->items);
	}

}