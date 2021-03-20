<?php
use Slim\Routing\RouteCollectorProxy;

/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Jobs
 * Shows job information and job posting option
 */

$app->group('/jobs', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

   // get job post form
    $group->get('/new', function ($request, $response, $args) use ($app) {
        global $lang;
        $token = token();
        $seo_title = 'Post new job | '. APP_NAME;
        $seo_desc = 'Post a new job at '. APP_NAME;
        $seo_url = BASE_URL .'jobs/new';

        $csrf = $this->get('csrf');
        return $this->get('PhpRenderer')->render($response, THEME_PATH . 'job.new.php',
                array('lang' => $lang,
                    'seo_url'=>$seo_url, 
                    'seo_title'=>$seo_title, 
                    'seo_desc'=>$seo_desc, 
                    'token'=>$token,
                    'markdown'=>ACTIVE,
                    'filestyle'=>ACTIVE,
                    'csrf_key'=> $request->getAttribute($csrf->getTokenNameKey()),
                    'csrf_keyname' => $csrf->getTokenNameKey(),
                    'csrf_token'=> $request->getAttribute($csrf->getTokenValueKey()),
                    'csrf_tokenname'=> $csrf->getTokenValueKey()));
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned']);

    // review job
    $group->post('/review' , function (\Slim\Psr7\Request $request, \Slim\Psr7\Response $response, $args) use ($app) {
        global $lang;
        $data = $request->getParsedBody(); //TODO
        $data = escape($data);

        if (Banlist::isBanned('email', $data['email'])
                || Banlist::isBanned('ip', $_SERVER['REMOTE_ADDR'])) {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|ip_banned'));
            return $response->withHeader('Location', BASE_URL . "jobs/new");
        }


        if ($data['trap'] != '') {
            return $response->withHeader('Location',BASE_URL . "jobs/new");
        }

        if (isset($_FILES['logo']) && $_FILES['logo']['name'] != '') {
            $file = $_FILES['logo'];
            $path = IMAGE_PATH;
            $data['logo'] = time() .'_'. $file['name'];
            $data['logo_type'] = $file['type'];
            $data['logo_size'] = $file['size'];
            $ext = strtolower(pathinfo($data['logo'], PATHINFO_EXTENSION));
            
            if (move_uploaded_file($file['tmp_name'], "{$path}{$data['logo']}") && isValidImageExt($ext)) {                
                $resize = new ResizeImage("{$path}{$data['logo']}");
                $resize->resizeTo(LOGO_H, LOGO_W);
                $resize->saveImage("{$path}thumb_{$data['logo']}");
            } else {
                 $data['logo'] = '';
            }
        } else {
            $data['logo'] = '';
        }
        
        $data['is_featured'] = (isset($data['is_featured'])) ? 1 : 0;
        
        $j = new Jobs();
        $data['step'] = 2;

        if ($data['email'] != '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $id = $j->jobCreateUpdate($data);
        }

        if (!isset($id)) {
            $this->get('flash')->addMessage('danger', $lang->t('alert|error_encountered'));//TODO
            return $response->withHeader('Location', BASE_URL ."jobs/new");
        } else {
            return $response->withHeader('Location', BASE_URL ."jobs/{$id}/edit/{$data['token']}");
        }
        
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned'])->add($mwHelpers['isValidReferrer']);

    // post job publish form
    $group->post('/{id}/publish[/{token}]', function (\Slim\Psr7\Request $request, $response, $args) use ($app) {
        
        global $lang;

        $data = $request->getParsedBody() ;//todo?
        $id = (int) $args['id'];
        $token = isset($args['token']) ? $args['token'] : '';

        $j = new Jobs($id);
        $job = $j->getJobFromToken($token);
        
        $data['is_featured'] = (isset($data['is_featured'])) ? 1 : 0;

        if ($data['trap'] != '' || !$job) {
            return $response->withHeader('Location', BASE_URL . "jobs/new");
        }
        if (isset($_FILES['logo']) && $_FILES['logo']['name'] != '') {
            $file = $_FILES['logo'];
            $path = IMAGE_PATH;
            $data['logo'] = time() .'_'. $file['name'];
            $data['logo_type'] = $file['type'];
            $data['logo_size'] = $file['size'];
            
            $ext = strtolower(pathinfo($data['logo'], PATHINFO_EXTENSION));
            if (move_uploaded_file($file['tmp_name'], "{$path}{$data['logo']}") && isValidImageExt($ext)) {     
                 $resize = new ResizeImage("{$path}{$data['logo']}");
                 $resize->resizeTo(LOGO_H, LOGO_W);
                 $resize->saveImage("{$path}thumb_{$data['logo']}");
            } else {
                 $data['logo'] = '';
            }
        } else {
            $data['logo'] = $job->logo;
        }
        
        $data['step'] = 3;
        
        $j->jobCreateUpdate($data);
        if (!$j->getStatus()) {
            $this->get('flash')->addMessage('success', $lang->t('alert|activation_email', $job->email));
        } else {
            $this->get('flash')->addMessage('success', $lang->t('alert|edit_successful'));
        }
        
        return $response->withHeader('Location', BASE_URL . "jobs/{$id}/publish/{$token}");
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned'])->add($mwHelpers['isValidReferrer']);
    
    // get publish job details 
    $group->get('/{id}/publish[/{token}]', function ($request, $response, $args) use ($app) {
        
        global $lang;
        $id = (int) $args['id'];
        $token = isset($args['token']) ? $args['token'] : '';

        $j = new Jobs($id);
        $job = $j->getJobFromToken($token);
        
        $title = $j->getSlugTitle();
        $city = $j->getJobCity($job->city);
        $category = $j->getJobCategory($job->category);
        
        if (isset($job) && $job->id) {
            
            $seo_title = clean($job->title) .' | '. APP_NAME;
            $seo_desc = excerpt($job->description);
            $seo_url = BASE_URL ."jobs/{$id}/{$title}";
            return $this->get('PhpRenderer')->render($response, THEME_PATH . 'job.publish.php',
                        array('lang' => $lang,
                            'flash'=>  $this->get('flash')->getMessages(),
                            'seo_url'=>$seo_url, 
                            'seo_title'=>$seo_title, 
                            'seo_desc'=>$seo_desc, 
                            'job'=>$job, 
                            'city'=>$city, 
                            'category'=>$category));
        } else {
            $this->get('flash')->addMessage('danger', $lang->t('alert|error_encountered'));
            return $response->withHeader('Location', BASE_URL . "jobs/{$id}/{$title}");
        }        
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned'])->add($mwHelpers['isValidReferrer']);
    
    // edit job
    $group->get('/{id}/edit[/{token}]', function ($request, $response, $args) use ($app) {
        
        global $lang;
        $data = $request->getParsedBody(); //TODO

        if (!isset($args['id'])) {
            $this->get('flash')->addMessage('danger', $lang->t('alert|error_encountered'));
            return $response->withHeader('Location', BASE_URL ."jobs/new");
        } else {
            $id = (int) $args['id'];
        }

        $j = new Jobs($id);
        $job = $j->getJobFromToken($args['token']);
        $title = $j->getSlugTitle();
        if (isset($job) && $job) {
            $seo_title = 'Edit job | '. APP_NAME;
            $seo_desc = APP_DESC;
            $seo_url = BASE_URL;
            $csrf = $this->get('csrf');
            return $this->get('PhpRenderer')->render($response, THEME_PATH . 'job.review.php',
                        array('lang' => $lang,
                            'seo_url'=>$seo_url, 
                            'seo_title'=>$seo_title, 
                            'seo_desc'=>$seo_desc, 
                            'job'=>$job,
                            'markdown'=>ACTIVE,
                            'filestyle'=>ACTIVE,
                            'csrf_key'=> $request->getAttribute($csrf->getTokenNameKey()),
                            'csrf_keyname' => $csrf->getTokenNameKey(),
                            'csrf_token'=> $request->getAttribute($csrf->getTokenValueKey()),
                            'csrf_tokenname'=> $csrf->getTokenValueKey()));
        } else {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|error_encountered'));
            return $response->withHeader('Location', BASE_URL . "jobs/{$id}/{$title}");
        }

    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned']);
    
    // delete existing job TODO
    $group->get('/{id}/delete[/{token}]', function ($request, $response, $args) use ($app) {
        
        global $lang;
        $id = (int) $args['id'];
        $token = isset($args['token']) ? $args['token'] : '';

        $j = new Jobs($id);
        if ($j->deleteJob($token)) {
            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|delete_success', $id));
            return $response->withHeader('Location', BASE_URL);
        } else {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|delete_error', $id));
            return $response->withHeader('Location', BASE_URL);
        }
        
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned']);
    
    // activate job
    $group->get('/{id}/activate[/{token}]', function ($request, $response, $args) use ($app) {
        
        global $lang;

        $id = (int) $args['id'];
        $token = isset($args['token']) ? $args['token'] : '';

        $j = new Jobs($id);
        $title = $j->getSlugTitle();
        
        if ($j->activateJob($token)) {
            
            $notif = new Notifications();
            $notif->sendEmailsToSubscribersMail($id);
            
            $job = $j->showJobDetails();
            $this->get('flash')->addMessage('success', $lang->t('admin|activate_success', $id));
            return $response->withHeader('Location', BASE_URL . "jobs/{$id}/{$title}");
        } else {
            $this->get('flash')->addMessage('danger', $lang->t('admin|activate_error', $id));
            return $response->withHeader('location', BASE_URL);
        }
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned']);
    
    // deactivate job
    $group->get('/{id}/deactivate[/{token}]', function ($request, $response, $args) use ($app) {

        global $lang;
        $id = (int) $args['id'];
        $token = isset($args['token']) ? $args['token'] : '';

        $j = new Jobs($id);
        var_dump($j);
        if ($j->deactivateJob($token)) {
            $job = $j->showJobDetails();
            $title = $j->getSlugTitle();
            $this->get('flash')->addMessage('success', $lang->t('admin|deactivate_success', $id));
            return $response->withHeader('Location', BASE_URL);
        } else {
            $this->get('flash')->addMessage('danger', $lang->t('admin|deactivate_error', $id));
            return $response->withHeader('Location'. BASE_URL);
        }
    })->add($mwHelpers['isJobPostAllowed'])->add($mwHelpers['isBanned']);

    // show job information
    $group->get('/{id}[/{title}]',  function ($request, $response, $args) use ($app) {

        global $lang;
        $id = (int)$args['id'];
        $j = new Jobs($id);
        $job = $j->showJobDetails();
        $city = $j->getJobCity($job->city);
        $category = $j->getJobCategory($job->category);
        $applications = $j->countJobApplications();
        
        if (isset($job) && $job->id && $job->status==ACTIVE) {
            
            $seo_title = clean($job->title) .' | '. APP_NAME;
            $seo_desc = excerpt($job->description);
            $seo_url = BASE_URL ."jobs/{$args['id']}/{$args['title']}";
            return $this->get('PhpRenderer')->render($response, THEME_PATH . 'job.show.php',
                    array('lang' => $lang,
                        'flash'=>  $this->get('flash')->getMessages(),
                        'seo_url'=>$seo_url, 
                        'seo_title'=>$seo_title, 
                        'seo_desc'=>$seo_desc, 
                        'job'=>$job, 
                        'id'=>$id,
                        'applications'=>$applications,
                        'category'=>$category, 
                        'city'=>$city));
        } else {
            return $response->withHeader('Location', BASE_URL);
        }
    });
});