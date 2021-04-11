<?php

use Slim\Routing\RouteCollectorProxy;

/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Subscriptions
 * Create and manage email subscriptions
 */


$app->group('/subscribe', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

    $group->post('/new', function ($request, $response, $args) use ($app) {

        global $categories, $cities;
        global $lang;

        $data = $request->getParsedBody();
        $data = escape($data);

        $redirect = ($data['category_id'] > 0) ? 'categories' : 'cities';
        $id = ($data['category_id'] > 0) ? $data['category_id'] : $data['city_id'];
        if ($data['category_id'] > 0) {
            $subscription_for = $categories[$data['category_id']]['name'];
        } else {
            $subscription_for = $cities[$data['city_id']]['name'];
        }

        if ($data['trap'] == '') {
            $subscribe = new Subscriptions($data['email'], $data['category_id'], $data['city_id']);
            if ($subscribe->createSubscription($subscription_for)) {
                $app->getContainer()->get('flash')->addMessage('success', $lang->t('subscribe|confirm_email'));
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('subscribe|existing'));
            }
            return $response->withHeader('Location', BASE_URL . "{$redirect}/{$id}");
        } else {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('subscribe|not_allowed'));
            return $response->withHeader('Location', BASE_URL . "{$redirect}/{$id}");
        }
    });

    $group->get('/{id}/{action}/{token}', function ($request, $response, $args) use ($app) {

        global $lang;
        $id = (int)$args['id'];
        $action = isset($args['action']) ? $args['action'] : null;
        $token = isset($args['token']) ? $args['token'] : null;

        $status = ($action == 'confirm') ? ACTIVE : INACTIVE;
        $s = new Subscriptions('');
        $user = $s->getUserSubscription($id, $token);
        if ($user) {
            $s->updateSubscription($id, $status);
            if ($status == ACTIVE) {
                $app->getContainer()->get('flash')->addMessage('success', $lang->t('subscribe|confirmed'));
            } else {
                $app->getContainer()->get('flash')->addMessage('success', $lang->t('subscribe|cancel'));
            }
            return $response->withHeader('Location', BASE_URL);
        } else {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('subscribe|confirm_error'));
            return $response->withHeader('Location', BASE_URL);
        }

    });

})->add($mwHelpers['isBanned']);