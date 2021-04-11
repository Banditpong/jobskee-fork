<?php
/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 */

/*
 * Load the configuration file
 */

require 'config.php';
/*
 * Load category and city values
 */
$categories = Categories::findCategories();
$cities = Cities::findCities();

/*
 * Load all existing controllers
 */
foreach (glob(CONTROLLER_PATH . "*.php") as $controller) {
    require_once $controller;
}
$app->addErrorMiddleware(true, true, true);

/*
 * Homepage
 * Front page controller
 */
$app->get('/[{page}]', function ($request, $response, $args) use ($app) {
    global $categories;
    global $lang;
    if (isset($args['page']) && $args['page'] != '') {
        $content = R::findOne('pages', ' url=:url ', array(':url' => $args['page']));
        if ($content && $content->id) {
            // show page information
            $seo_title = $content->name . ' | ' . APP_NAME;
            $seo_desc = excerpt($content->description);
            $seo_url = BASE_URL . $args['page'];

            return $this->get('PhpRenderer')->render($response, THEME_PATH . 'page.php', array(
                'lang' => $lang,
                'seo_url' => $seo_url,
                'seo_title' => $seo_title,
                'seo_desc' => $seo_desc,
                'content' => $content,
                'flash' => $this->get('flash')->getMessages(),
                'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
            ));
        } else {
            $this->get('flash')->addMessage('danger', $lang->t('alert|page_not_found'));
            return $response->withHeader('Location', BASE_URL);
        }
    } else {
        // show list of job
        $seo_title = APP_NAME;
        $seo_desc = APP_DESC;
        $seo_url = BASE_URL;

        $j = new Jobs();
        foreach ($categories as $cat) {
            $jobs[$cat->id] = $j->getJobs(ACTIVE, $cat->id, 0, HOME_LIMIT);
        }

        return $this->get('PhpRenderer')->render($response, THEME_PATH . 'home.php', array(
            'lang' => $lang,
            'flash' => $this->get('flash')->getMessages(),
            'seo_url' => $seo_url,
            'seo_title' => $seo_title,
            'seo_desc' => $seo_desc,
            'jobs' => $jobs,
            'flash' => $this->get('flash')->getMessages(),
            'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
            'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
            'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
            'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
        ));
    }
});

// Run app
$app->run();