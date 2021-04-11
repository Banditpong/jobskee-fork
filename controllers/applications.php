<?php
/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Applications
 * Job application form submission
 */

use Slim\Routing\RouteCollectorProxy;

$app->group('/apply', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

    // get job post form
    $group->get('/{job_id}[/]', function ($request, $response, $args) use ($app) {

        global $lang;
        $job_id = isset($args['job_id']) ? intval($args['job_id']) : null;

        $token = token();

        $seo_title = $lang->t('apply|seo_title') . ' | ' . APP_NAME;
        $seo_desc = $lang->t('apply|seo_desc') . ' | ' . APP_NAME;
        $seo_url = BASE_URL . 'apply/new';

        $job = new Applications($job_id);
        $title = $job->getJobTitle();
        return $this->get('PhpRenderer')->render($response, THEME_PATH . 'apply.new.php',
            array(
                'lang' => $lang,
                'flash' => $this->get('flash')->getMessages(),
                'seo_url' => $seo_url,
                'seo_title' => $seo_title,
                'seo_desc' => $seo_desc,
                'token' => $token,
                'job_id' => $job_id,
                'job_title' => $title,
                'filestyle' => ACTIVE,
                'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
            ));
    });

    // submit job application
    $group->post('/submit', function ($request, $response, $args) use ($app) {

        global $lang;

        $data = $request->getParsedBody();
        $data = escape($data);

        if (Banlist::isBanned('email', $data['email'])
            || Banlist::isBanned('ip', $_SERVER['REMOTE_ADDR'])) {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('apply|email_ip_banned'));
            return $response->withHeader('Location', BASE_URL . "apply/{$data['job_id']}");
        }

        if ($data['trap'] != '') {
            return $response->withHeader('Location', BASE_URL . "apply/{$data['job_id']}");
        }

        if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] != '') {
            $file = $_FILES['attachment'];
            $path = ATTACHMENT_PATH;
            $attachment = time() . '_' . $file['name'];
            $data['attachment_type'] = $file['type'];
            $data['attachment_size'] = $file['size'];

            if (move_uploaded_file($file['tmp_name'], "{$path}{$attachment}")) {
                $data['attachment'] = $attachment;
            }
        } else {
            $data['attachment'] = '';
        }

        $apply = new Applications($data['job_id']);
        if ($apply->applyForJob($data)) {
            $app->getContainer()->get('flash')->addMessage('success', $lang->t('apply|msg_success'));
        } else {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('apply|msg_fail'));
        }
        $title = $apply->getJobTitleURL();
        return $response->withHeader('Location', BASE_URL . "jobs/{$data['job_id']}/{$title}");
    })->add($mwHelpers['isValidReferrer']);

})->add($mwHelpers['isBanned']);