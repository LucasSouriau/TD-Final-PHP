<?php

use Gregwar\Image\Image;

$app->match('/', function () use ($app) {
    return $app['twig']->render('home.html.twig');
})->bind('home');

$app->match('/books', function () use ($app) {
    return $app['twig']->render('books.html.twig', array(
        'books' => $app['model']->getBooks()
    ));
})->bind('books');

$app->match('/admin', function () use ($app) {
    $request = $app['request'];
    $success = false;
    if ($request->getMethod() == 'POST') {
        $post = $request->request;

        if ($post->has('login') && $post->has('password')) {
            $admins = $app['config']['admin'];
            foreach ($admins as $login => $password) {
                if (array($post->get('login'), $post->get('password')) == array($login, $password)) {
                    $success = true;
                }
            }
            if ($success) {
                $app['session']->set('admin', true);
            }

        }
    }
    return $app['twig']->render('admin.html.twig', array(
        'success' => $success
    ));
})->bind('admin');

$app->match('/logout', function () use ($app) {
    $app['session']->remove('admin');
    return $app->redirect($app['url_generator']->generate('admin'));
})->bind('logout');

$app->match('/addBook', function () use ($app) {
    if (!$app['session']->has('admin')) {
        return $app['twig']->render('shouldBeAdmin.html.twig');
    }

    $request = $app['request'];
    if ($request->getMethod() == 'POST') {
        $post = $request->request;
        if ($post->has('title') && $post->has('author') && $post->has('synopsis') &&
            $post->has('copies')
        ) {
            $files = $request->files;
            $image = '';

            // Resizing image
            if ($files->has('image') && $files->get('image')) {
                $image = sha1(mt_rand() . time());
                Image::open($files->get('image')->getPathName())
                    ->resize(240, 300)
                    ->save('uploads/' . $image . '.jpg');
                Image::open($files->get('image')->getPathName())
                    ->resize(120, 150)
                    ->save('uploads/' . $image . '_small.jpg');
            }

            // Saving the book to database
            $app['model']->insertBook($post->get('title'), $post->get('author'), $post->get('synopsis'),
                $image, (int)$post->get('copies'));
        }
    }

    return $app['twig']->render('addBook.html.twig');
})->bind('addBook');

// Fiche livre
$app->match('/livre/{id}', function ($id) use ($app) {

    return $app['twig']->render('book.html.twig', [
        'book' => $app['model']->getBook($id),
        'exemplaires' => $app['model']->getExemplaires($id)
    ]);
})->bind('livre');


$app->match('/borrow/{id}', function ($id) use ($app) {

    $request = $app['request'];
    $success = false;

    if ($request->getMethod() == 'POST') {
        $post = $request->request;
        if ($post->has('name') && $post->has('fin')) {

            $date = explode('/', $post->get('fin'));
            if(sizeof($date) == 3){
                $success = true;
            }

            if ($success) {
                // Saving the book to database
                $app['model']->insertBorrow($id, $post->get('name'), $date[2] . "-" . $date[1] . "-" . $date[0]);

                return $app->redirect($app["url_generator"]->generate("livre", array("id" => $app['model']->getBookExemplaire($id)['book'])));
            } else {
                return $app['twig']->render('borrow.html.twig', [
                    "success" => $success
                ]);
            }
        }
    }

    return $app['twig']->render('borrow.html.twig');
})->bind('borrow');


$app->match('/unborrow/{id}', function ($id) use ($app) {

    $app['model']->unBorrow($id);
    return $app->redirect($app["url_generator"]->generate("livre", array("id" => $app['model']->getBookExemplaire($id)['book'])));

})->bind('unborrow');



