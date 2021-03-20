<?php
use Slim\Routing\RouteCollectorProxy;
/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Cron
 * List of available cron jobs
 */

$app->group('/cron', function (RouteCollectorProxy $group) use ($app) {
    
    // expire jobs
    $group->get('/jobs/expire/{cron_token}', function ($request, $response, $args) use ($app) {
        
        if (trim($args['cron_token']) == CRON_TOKEN) {
            $j = new Jobs();
            $j->expireJobs();
            echo true;
            exit();
        }
    });
    
});
