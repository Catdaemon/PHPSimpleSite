<?php
// Based on https://github.com/JREAM/route/blob/master/route.php which no longer exists.
// Small improvements.

class Router
{
	private static $routes = array();

	public static function addRoute($path, $function)
	{
		self::$routes[$path] = $function;
	}

	public static function route($request = '/')
	{
		$uri = !empty($request) ? $request : '/';

		if ($uri != '/' && substr($uri, -1) != '/')
			$uri .= '/';

		foreach(self::$routes as $path => $func)
		{
			if (preg_match("#^{$path}$#", $uri))
			{
				$uriParts = explode('/', $uri);
				$routeParts = explode('/', $path);

				$values = array();

				foreach($routeParts as $k => $part)
					if ($part == '.+')
						$values[] = $uriParts[$k];

				call_user_func_array($func, $values);

				return true;
			}
		}

		return false;
	}
}
