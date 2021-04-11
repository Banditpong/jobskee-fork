<?php
/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Search
 * Search for jobs
 */

use Slim\Routing\RouteCollectorProxy;

$app->group('/search', function (RouteCollectorProxy $group) use ($app) {

    $group->post('[/]', function ($request, $response, $args) use ($app) {

        $data = $request->getParsedBody();
        $terms = escape($data['terms']);
        $terms = urlencode($terms);
        return $response->withHeader('Location', BASE_URL . "search/{$terms}");
    });

    $group->get('/{terms}', function ($request, $response, $args) use ($app) {
        global $lang;
        $s = new Search();

        $jobs = $s->searchJobs($args['terms']);
        $count = $s->countJobs($args['terms']);

        return $this->get('PhpRenderer')->render($response, THEME_PATH . 'search.php',
            array(
                'lang' => $lang,
                'terms' => $args['terms'],
                'count' => $count,
                'seo_url' => BASE_URL . 'search',
                'seo_title' => $lang->t('search|search_result') . ' ' . APP_NAME,
                'seo_desc' => $lang->t('search|search_result') . ' ' . APP_NAME,
                'jobs' => $jobs,
                'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
            ));
    });
});