<?php
/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Categories
 * Shows jobs per category
 */
use Slim\Routing\RouteCollectorProxy;

$app->group('/categories', function (RouteCollectorProxy $group) use ($app) {
    // get categories index
    $group->get('[/]', function () use ($app) {
        $response = $app->getResponseFactory()->createResponse();
        return $response->withHeader('Location', BASE_URL);
    });

    // rss category jobs
    $group->get('/{id}/{name}/rss[/]', function ($request, $response, $args) use ($app) {

        global $lang;
        $id = (int) $args['id'];
        $cat = new Categories($id);
        $info = $cat->findCategory();

        $jobs = $cat->findAllCategoryJobs();

        $xml = new SimpleXMLElement('<rss version="2.0"></rss>');
        $xml->addChild('channel');
        $xml->channel->addChild('title', htmlentities(escapeXML($info->name)) ." ". $lang->t('jobs|jobs') .' | '. APP_NAME); 
        $xml->channel->addChild('link', BASE_URL . "categories/{$info->id}/{$info->url}");
        $xml->channel->addChild('description', htmlentities(escapeXML($info->description))); 
        foreach ($jobs as $job) { 
            $item = $xml->channel->addChild('item'); 
            $item->addChild('title', htmlentities(escapeXML($job->title))); 
            $item->addChild('link', BASE_URL . "jobs/{$job->id}/". slugify($job->title ." {$lang->t('jobs|at')} ". $job->company_name));
            $item->addChild('description', htmlentities(escapeXML($job->description)));
            $guid = $item->addChild('guid', $job->id .'@' . BASE_URL); 
            $guid->addAttribute('isPermaLink', "false");
            $item->addChild('pubDate', date(DATE_RSS, strtotime($job->created))); 
        }
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML(html_entity_decode($xml->asXML()));
        echo $dom->saveXML();
        return $response; //'Content-Type', 'application/rss+xml;charset=utf-8'
    });

    // get category jobs
    $group->get('/{id}[/[{name}[/[{page}]]]]', function ($request, $response, $args) use ($app) {
        global $lang;
        $id = (int)$args['id'];
        $cat = new Categories($id);
        $categ = $cat->findCategory();
        $name = isset($args['name']) ? htmlentities($args['name']) : '';
        $page = isset($args['page'])? (int)$args['page'] : 1;

        if (isset($categ) && $categ) {
            $start = getPaginationStart();
            $count = $cat->countCategoryJobs($page);
            $number_of_pages = ceil($count/LIMIT);
            $jobs = $cat->findCategoryJobs($start, LIMIT);

            $seo_title = $categ->name .' | '. APP_NAME;
            $seo_desc = excerpt($categ->description);
            $seo_url = BASE_URL ."categories/{$id}/{$name}";
            $csrf = $this->get('csrf');
            return $this->get('PhpRenderer')->render($response, THEME_PATH . 'categories.php',
                array('lang' => $lang,
                    'flash'=>  $this->get('flash')->getMessages(),
                    'seo_url'=>$seo_url,
                    'seo_title'=>$seo_title,
                    'seo_desc'=>$seo_desc,
                    'categ'=>$categ,
                    'jobs'=>$jobs,
                    'id' => $id,
                    'number_of_pages'=>$number_of_pages,
                    'current_page'=>$page,
                    'page_name'=>'categories',
                    'csrf_key'=> $request->getAttribute($csrf->getTokenNameKey()),
                    'csrf_keyname' => $csrf->getTokenNameKey(),
                    'csrf_token'=> $request->getAttribute($csrf->getTokenValueKey()),
                    'csrf_tokenname'=> $csrf->getTokenValueKey()));
        } else {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|page_not_found'));
            return $response->withHeader('Location', BASE_URL); //TODO: 404
        }
    });
});