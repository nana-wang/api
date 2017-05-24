<?php
/**
 * Collections let us define groups of routes that will all use the same controller.
 * We can also set the handler to be lazy loaded.  Collections can share a common prefix.
 */
return call_user_func(function () {
    $tokenCollection = new \Phalcon\Mvc\Micro\Collection();
    $tokenCollection->setPrefix('/v1/access_token')
        ->setHandler('\DwComment\Modules\V1\Controllers\AccessTokenController')
        ->setLazy(true);
    // Set Access-Control-Allow headers.
    $tokenCollection->post('/', 'post');
    return $tokenCollection;
});