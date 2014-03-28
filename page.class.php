<?php

// Base class for pages.
class Page
{
	public $name = '';
	public $title = '';
	public $data = array();
	public $template = '';

	// Return an instaoce of a page by name, and initialize if found. Otherwise returns false
	static function load($name)
	{
		$name	=	strtolower($name);
		$name	=	preg_replace("/[^a-z0-9 ]/", '', $name);

		if (file_exists("pages/$name.php"))
		{
			include("pages/$name.php");
			$name = 'Page_'.$name;

			$page = new $name;

			// Allow the template to reference the page for convenience.
			$page->data['PAGE'] = $page;

			return $page;
		}

		return false;
	}

	protected function setTitle($_title)
	{
		$this->title = $_title;
	}

	protected function setTemplate($_template)
	{
		$this->template = $_template;
	}

	// Assigns data to the template
	public function assign($key,$value)
	{
		$this->data[$key] = $value;
	}

	// Redirects with correct headers, and halts execution
	protected function redirect($url = '/')
	{
		session_write_close();
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: ' . $url);
		die();
	}

	// Responds with correct headers and sets the template to ether error_notfound.html or a specified alternative
	public function notFound($template='error_notfound.html')
	{
		header('HTTP/1.1 404 Not Found');
		$this->setTemplate($template);
	}

	// Assigns an 'errors' variable and sets the template to error_generic.html or a specified alternative
	public function error($errors, $template='error_generic.html')
	{
		$this->assign('errors', $errors);
		$this->setTemplate($template);
	}

	// Ensure the user is viewing the SSL version of the page
	public function requireSSL()
	{
		if($_SERVER["HTTPS"] != "on")
			$this->redirect("https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
	}

	// Overwrite
	public function index()
	{
		return false;
	}
}