<?php namespace Weisd\Alipay\Facades;

use Illuminate\Support\Facades\Facade;

class Alipay extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() {return 'alipay';}

}