# Overview
This is a small PHP framework made up of bits and pieces to aid in rapidly developing small sites or prototypes. Model fields are not defined in code, which adds overhead to model commits but makes it easier to fiddle with the database. It is designed for situations in which development time is of more importance than performance, but it does perform reasonably well. Keep in mind that queries are not automatically optimised, so if you have heavy database work to do you'll need to do that elsewhere.

A template engine is not included, and is not necessary.

# Example Usage
We're making a website with a blog. It will use Twig as the template engine. This is an example and won't function as-is, but it gives you the general idea.

## Models
This is a simple blog, with optional gallery images. We need the following models, which will reflect the database we made earlier.
### ./classes/models/BlogPost.php
This file creates a model for a blog post, and a method to find its images.
```php
    <?php
    class BlogPost extends DBO
    {
        // Primary key is used by convenience functions
        public static $PK = 'PostID';

        public function getImages()
        {
            $data = dbExt::getInstance()->run("SELECT * FROM BlogPostImage WHERE PostID = ?", array($this->PostID));
            return BlogPostImage::fromResultSet($data);
        }
    }
    ?>
```

### ./classes/models/BlogPostImage.php
This file creates a model for blog post images. As you can see, it's quite sparse as most of the work is done by the DBO class.
```php
    <?php

    class BlogPostImage extends DBO
    {
        public static $PK = 'ImageID';
    }
    ?>
```

## Page Controllers
### ./pages/blog.php
This page contains two methods, one to list paginated posts and one to view a single post.
```php
    <?php

    class Page_Blog extends Page
    {
        var $name = 'blog';

        function index($page)
        {
            if (!is_numeric($page))
                $this->notFound();

            $this->setTitle('Blog');

            $limit = 5; // Items per page

            $data = BlogPost::getPage($page, $limit);
            $pages = BlogPost::getPageCount($limit);

            $this->assign('posts', $data);
            $this->assign('currentPage', $page);
            $this->assign('totalPages', $pages);

            $this->setTemplate('blog.html');
        }

        function view($id)
        {
            $post = BlogPost::fromID($id);

            if (!$post)
            {
                $this->notFound();
                return;
            }

            $urlAppend = str_replace(' ', '-', $post->PostTitle);

            // Add the name of the post to the end of the URL
            if (!(substr($_SERVER['REQUEST_URI'], -strlen($urlAppend))===$urlAppend))
                $this->redirect("/blog/{$post->PostID}/{$urlAppend}");

            $this->setTitle('Blog Post: ' . $post->PostTitle);

            $this->assign('post', $post);
            $this->assign('next', BlogPost::fromID($id + 1));
            $this->assign('previous', BlogPost::fromID($id - 1));
            $this->setTemplate('blog_post.html');
        }
    }
    ?>
```

### ./pages/home.php
This is the home page, and shows the latest blog post.
```php
<?php

class Page_Home extends Page
{
    var $name = 'home';
    
    function index()
    {
        $this->setTitle('Home');

        $post = BlogPost::getLast();
        $this->assign('post', $post);
        
        $this->setTemplate('home.html');
    }
}
?>
```

## Views/Templates
As this blog uses Twig for templates, they will look like this.

### ./templates/global.tpl
This template defines all the elements each page will share.
```html
<!DOCTYPE html>
<html>
    <head>
        <title>{{ PAGE.title }} &bull; My Blog</title>
        <link rel='icon' href='/favicon.ico'>

        <link rel='stylesheet' href='/static/style.css'>
    </head>
    <body>
        <header>
            <h1>My Blog</h1>
            <h2>It's an okay blog, I suppose.</h2>
        </header>

        <nav>
            <a href='/' {% if PAGE.name == 'home' %}class='active'{% endif %}>Home</a>
            <a href='/blog/' {% if PAGE.name == 'blog' %}class='active'{% endif %}>Blog</a>
        </nav>

        <div>
            {% block content %}
            {% endblock %}
        </div>

        <footer>
            <p>&copy; {{ 'now'|date('Y') }}</p>
        </footer>
    </body>
</html>
```

### ./templates/home.html
```html
{% extends "global.html" %}

{% block content %}
    <h1>About Me</h1>
    <article>
        <p>I am a human being, possibly.</p>
    </article>

    <h1>Latest Blog Post</h1>

    {% include 'blog_post_bit.html' %}
{% endblock %}
```

### ./templates/blog.html
```html
{% extends 'global.html' %}

{% block content %}
    <h1>Blog</h1>

    {% for post in posts %}
        {% include 'blog_post_bit.html' %}
    {% else %}
        <p>No posts found.</p>
    {% endfor %}

    {% if totalPages > 1 %}
        <a href='/blog/page/1/'>First</a>
        {% for page in 1..totalPages if page > 1 and (page > currentPage - 5 or page < currentPage + 5) and page != totalPages %}
            <a href='/blog/page/{{ page }}/'>{{ page }}</a>
        {% endfor %}
        <a href='/blog/page/{{ totalPages }}/'>Last</a>
    {% endif %}
{% endblock %}
```

### ./templates/blog_post.html
```html
{% extends 'global.html' %}

{% block content %}
    <h1>Blog Post</h1>

    {% include 'blog_post_bit.html' with {'singlepost': true} %}

    <h1>More</h1>
    <article>
        {% if next.PostTitle %}
            <p><a href='/blog/{{ next.PostID }}'>&raquo; {{ next.PostTitle }}</a></p>
        {% endif %}
        {% if previous.PostTitle %}
            <p><a href='/blog/{{ previous.PostID }}'>&laquo; {{ previous.PostTitle }}</a></p>
        {% endif %}
    </article>
{% endblock %}
```

I won't bother including blog_post_bit.html, as if you've followed this far it should be pretty obvious how it works.

## Index page
This is where it all comes together. It does the following:
- Load libraries
- Connect to the database
- Define routes
- Run the route
    - Load the relevant page
    - Execute the relevant method
- Display the template

```php
<?php
    require('lib/SimpleSite/loadAll.php');
    require('lib/Twig/Autoloader.php');

    Twig_Autoloader::register();

    $loader = new Twig_Loader_Filesystem('./templates/');

    $twig = new Twig_Environment($loader, array(
        //'cache' => 'templates/cache/',
    ));

    $page;

    // This function connects and returns an error string if there's an error.
    $dberror = dbExt::connect('mysql:host=localhost;dbname=mydb', 'myblog', 'mypassword');

    // If there's a problem connecting, load the "error" page and run the "database" method
    if (is_string($dberror))
    {
        $page = Page::load('error');
        $page->database($dberror);
    } else {

        // Load the home page as the index
        Router::addRoute('/', function() use (&$page) {
            $page = Page::load('home');
            $page->index();
        });

        // Load page 1 of the blog by default
        Router::addRoute('/blog/', function() use (&$page) {
            $page = Page::load('blog');
            $page->index(1);
        });

        // Load the specified blog page
        Router::addRoute('/blog/page/.+/', function($pageNo) use (&$page) {
            $page = Page::load('blog');
            $page->index($pageNo);
        });

        // Load the specified post
        Router::addRoute('/blog/.+/', function($n) use (&$page) {
            $page = Page::load('blog');
            $page->view($n);
        });

        // Plug the request URI into the router. This assumes the web server points all not-found requests at index.php.
        if (!Router::route($_SERVER['REQUEST_URI']) && empty($dberror) || empty($page))
        {
            $page = Page::load('error');
            $page->notfound();
        }
    }

    // Site specific processing here

    // Render the template
    $template = $twig->loadTemplate($page->template);
    $template->display($page->data);
?>
```

# License
Do whatever you like with this code. Print it out and sell it as a hat if that's what you want to do. If you *need* a license then you can consider it MIT licensed.

gl hf