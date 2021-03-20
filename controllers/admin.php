<?php

use Slim\Routing\RouteCollectorProxy;

/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * Admin
 * Jobskee admin panel
 */


define('ADMIN_MANAGE', ADMIN_URL . 'manage');

/*
 * Admin
 * Show admin options
 */

$app->group('/admin', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {
    // admin default
    $group->get('/', function ($request, $response, $args) use ($app) {
        if (userIsValid()) {
            return $response->withHeader('Location', ADMIN_MANAGE);
        } else {
            return $response->withHeader('Location', LOGIN_URL);
        }
    });

    // login admin form
    $group->get('/login', function ($request, $response, $args) use ($app) {

        global $lang;
        //$csrf = $this->get('csrf') ;
        $val = array(
            'lang' => $lang,
            'flash' => $this->get('flash')->getMessages(),
            'seo_title' => APP_NAME,
            'seo_desc' => APP_DESC,
            'seo_url' => ADMIN_URL,
            'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
            'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
            'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
            'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
        );

        if (isset($args['user'])) {
            if (trim($args['user']) == 'invalid') {
                $val['invalid'] = 'yes';
            }
        }

        return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'login.php', $val);
    });

    // authenticate user
    $group->post('/authenticate', function ($request, $response, $args) use ($app) {

        global $lang;

        $data = $request->getParsedBody();//TODO:

        $admin = R::findOne('admin', ' email=:email AND password=:password ',
            array(':email' => $data['email'], ':password' => sha1($data['password'])));
        if (isset($admin) && $admin->id) {

            $_SESSION['is_admin'] = true;
            $_SESSION['email'] = $data['email'];
            return $response->withHeader('Location', ADMIN_MANAGE);
        } else {
            $this->get('flash')->addMessage('danger', $lang->t('admin|invalid_login'));
            return $response->withHeader('Location', LOGIN_URL);
        }
    })->add($mwHelpers['isValidReferrer']);

    // logout admin
    $group->get('/logout', function ($request, $response, $args) use ($app) {

        global $lang;

        unset($_SESSION['email']);
        unset($_SESSION['is_admin']);
        $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|logout_success'));//TODO
        return $response->withHeader('Location', LOGIN_URL);
    })->add($mwHelpers['validateUser']);

    /*
     * Manage group
     * Manage inactive jobs, categories list, cities list
     */
    $group->group('/manage', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        // manage inactive jobs
        $group->get('[/]', function ($request, $response, $args) use ($app) {

            global $categories;
            global $lang;
            $j = new Jobs();
            foreach ($categories as $cat) {
                $jobs[$cat->id] = $j->getJobs(INACTIVE, $cat->id);
            }

            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'home.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'jobs' => $jobs
                ));
        });

        /*
        * Manage categories group
        */
        $group->group('/categories', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

            $group->post('[/]', function ($request, $response, $args) use ($app) {

                global $lang;

                $data = $request->getParsedBody();
                $id = isset($data['id']) ? intval($data['id']) : null;

                $c = new Categories($id);
                $c->addCategory($data);
                if ($data && $id != null) {
                    $message = $lang->t('admin|category_update');
                } else {
                    $message = $lang->t('admin|category_new');
                }
                $app->getContainer()->get('flash')->addMessage('success', $message);
                return $response->withHeader('Location', ADMIN_MANAGE . '/categories');

            })->add($mwHelpers['isValidReferrer']);

            $group->get('[/[{id}[/[{action}]]]]', function ($request, $response, $args) use ($app) {
                global $lang;

                $id = isset($args['id']) ? intval($args['id']) : null;
                $action = isset($args['action']) ?  $args['action']: null; //TODO
                $category = null;

                if ($id && $action == 'edit') {
                    $c = new Categories($id);
                    $category = $c->findCategory();
                } elseif ($id && $action == 'delete') {
                    $c = new Categories($id);
                    if ($c->deleteCategory()) {
                        $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|category_delete'));
                    } else {
                        $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|category_not_delete'));
                    }
                    return $response->withHeader('Location', ADMIN_MANAGE . '/categories');
                }

                $categories = Categories::findCategories();
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'categories.edit.php',
                    array('lang' => $lang, 'flash'=>  $this->get('flash')->getMessages(), 'categs' => $categories, 'category' => $category,
                        'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                        'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                        'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                        'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
            });
        });

        /*
         * Manage cities group
         */
        $group->group('/cities', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

            $group->post('[/]', function ($request, $response, $args) use ($app) {

                global $lang;

                $data = $request->getParsedBody();
                $id = isset($data['id']) ? intval($data['id']) : null;

                $c = new Cities($id);
                $c->addCity($data);
                if ($data && $data['id'] != null) {
                    $message = $lang->t('admin|city_update');
                } else {
                    $message = $lang->t('admin|city_new');
                }
                $app->getContainer()->get('flash')->addMessage('success', $message);
                return $response->withHeader('Location', ADMIN_MANAGE . '/cities');
            })->add($mwHelpers['isValidReferrer']);

            $group->get('[/[{id}[/[{action}]]]]', function ($request, $response, $args) use ($app) {

                global $lang;
                $id = isset($args['id']) ? intval($args['id']) : null;
                $action = isset($args['action']) ?  $args['action']: null; //TODO
                $city = null;

                $c = new Cities($id);
                if ($id && $action == 'edit') {
                    $city = $c->findCity();
                } elseif ($id && $action == 'delete') {
                    $c->deleteCity();
                    if ($c->deleteCity()) {
                        $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|city_delete'));
                    } else {
                        $app->getContainer()->get('flash')->addMessage( 'danger', $lang->t('admin|city_not_delete'));
                    }
                    return $response->withHeader('Location', ADMIN_MANAGE . '/cities');
                }
                $cities = Cities::findCities();
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'cities.edit.php',
                    array('lang' => $lang, 'cits' => $cities, 'city' => $city, 'flash' => $this->get('flash')->getMessages(),
                        'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                        'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                        'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                        'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));

            });

        });


    })->add($mwHelpers['validateUser']);

    /*
     * Jobs group
     * Admin jobs routes
     */
    $group->group('/jobs', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        // upload jobs from csv
        $group->get('/upload', function ($request, $response, $args) use ($app) {

            global $lang;
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'upload.php', array('lang' => $lang,
                'flash'=>  $this->get('flash')->getMessages(),
                'filestyle' => ACTIVE,
                'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

        // process csv file
        $group->post('/upload', function ($request, $response, $args) use ($app) {

            global $lang;

            $data = array();
            if (isset($_FILES['csv']) && $_FILES['csv']['name'] != '') {
                $file = $_FILES['csv'];
                $path = ATTACHMENT_PATH;
                $data['csv'] = time() . '_' . $file['name'];
                $data['type'] = $file['type'];
                $data['csv_size'] = $file['size'];

                $csv = "{$path}{$data['csv']}";

                if ($data['type'] == 'text/csv' && move_uploaded_file($file['tmp_name'], $csv)) {

                    $added = 0;
                    $skipped = 0;

                    $file = new SplFileObject($csv);
                    $file->setFlags(SplFileObject::READ_CSV);
                    foreach ($file as $data) {

                        // check if the csv file has the right number of fields
                        if (count($data) == CSV_FIELDS) {

                            $featured = (isset($data[9]) && $data[9] == 'featured') ? 1 : 0;

                            $job = array(
                                'title' => $data[0],
                                'category' => $data[1],
                                'city' => $data[2],
                                'description' => $data[3],
                                'perks' => $data[4],
                                'how_to_apply' => $data[5],
                                'company_name' => $data[6],
                                'url' => $data[7],
                                'email' => $data[8],
                                'logo' => '',
                                'is_featured' => $featured,
                                'token' => token(),
                                'step' => 3
                            );

                            $j = new Jobs();
                            $j->jobCreateUpdate($job, ACTIVE);

                            $added++;
                        } else {
                            $skipped++;
                        }
                    }

                    // remove csv file
                    if (file_exists($csv)) {
                        unlink($csv);
                    }
                    $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|upload_success', $added));
                    return $response->withHeader('Location', ADMIN_URL . 'jobs/upload');

                } else {
                    $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|upload_invalid'));
                    return $response->withHeader('Location', ADMIN_URL . 'jobs/upload');
                }
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|upload_none'));
                return $response->withHeader('Location', ADMIN_URL . 'admin/upload');
            }

        })->add($mwHelpers['isValidReferrer']);

        // expire jobs after X days
        $group->get('/expire', function ($request, $response, $args) use ($app) {

            global $lang;

            $j = new Jobs();
            $j->expireJobs();

            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|expire_success'));
            return $response->withHeader('Location', ADMIN_MANAGE);
        });//'validateUser',

        // get job post form
        $group->get('/new', function (\Slim\Psr7\Request $request, $response, $args) use ($app) {

            global $lang;
            $token = token();
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'job.new.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'token' => $token,
                    'markdown' => ACTIVE,
                    'filestyle' => ACTIVE,
                    'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                    'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                    'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                    'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

        // review job
        $group->post('/review', function ($request, $response, $args) use ($app) {

            global $lang;

            $data = $request->getParsedBody();
            $data = escape($data);

            if ($data['trap'] != '') {
                return $response->withHeader('Location', ADMIN_URL . "jobs/new");
            }

            if (isset($_FILES['logo']) && $_FILES['logo']['name'] != '') {
                $file = $_FILES['logo'];
                $path = IMAGE_PATH;
                $data['logo'] = time() . '_' . $file['name'];
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

            $data['is_featured'] = (isset($data['is_featured'])) ? ACTIVE : INACTIVE;

            $j = new Jobs();
            $data['step'] = 2;
            $id = $j->jobCreateUpdate($data, ACTIVE);

            return $response->withHeader('Location', ADMIN_URL . "jobs/{$id}/edit/{$data['token']}");
        })->add($mwHelpers['isValidReferrer']);

        // post job publish form
        $group->post('/{id}/publish/{token}', function ($request, $response, $args) use ($app) {

            global $lang;

            $data = $request->getParsedBody();
            $data = escape($data);
            $id = isset($data['id']) ? intval($data['id']) : null;
            $token = $data['token'];

            $j = new Jobs($id);
            $job = $j->getJobFromToken($token);

            $data['is_featured'] = (isset($data['is_featured'])) ? 1 : 0;

            if ($data['trap'] != '') {
                return $response->withHeader('Location', ADMIN_URL . "jobs/new");
            }

            if (isset($_FILES['logo']) && $_FILES['logo']['name'] != '') {
                $file = $_FILES['logo'];
                $path = IMAGE_PATH;
                $data['logo'] = time() . '_' . $file['name'];
                $data['logo_type'] = $file['type'];
                $data['logo_size'] = $file['size'];

                $ext = strtolower(pathinfo($data['logo'], PATHINFO_EXTENSION));
                if (move_uploaded_file($file['tmp_name'], "{$path}{$data['logo']}") && isValidImageExt($ext)) {
                    $resize = new ResizeImage("{$path}{$data['logo']}");
                    $resize->resizeTo(LOGO_H, LOGO_W);
                    $resize->saveImage("{$path}thumb_{$data['logo']}");
                }
            } else {
                $data['logo'] = $job->logo;
            }

            $data['step'] = 3;
            $j->jobCreateUpdate($data, ACTIVE);
            return $response->withHeader('Location', ADMIN_URL . "jobs/{$id}/publish/{$token}");
        })->add($mwHelpers['isValidReferrer']);

        // get publish job details 
        $group->get('/{id}/publish/{token}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = (int) $args['id'];
            $token = $args['token'];

            $j = new Jobs($id);
            $job = $j->getJobFromToken($token);
            $title = $j->getSlugTitle();
            $city = $j->getJobCity($job->city);
            $category = $j->getJobCategory($job->category);
            if (isset($job) && $job->id) {
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'job.publish.php',
                    array(
                        'lang' => $lang,
                        'flash' => $this->get('flash')->getMessages(),
                        'job' => $job,
                        'city' => $city,
                        'category' => $category
                    ));
            } else {
                return $response->withHeader('Location', ADMIN_URL . "jobs/{$id}/{$title}");
            }
        });

        // edit job
        $group->get('/{id}/edit/{token}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = (int) $args['id'];
            $token = $args['token'];
            $j = new Jobs($id);
            $job = $j->getJobFromToken($token);
            if (isset($job) && $job->id) {
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'job.review.php',
                    array(
                        'lang' => $lang,
                        'flash' => $this->get('flash')->getMessages(),
                        'job' => $job,
                        'markdown' => ACTIVE,
                        'filestyle' => ACTIVE,
                        'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                        'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                        'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                        'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
            ));
            } else {
                $job = $j->showJobDetails();
                $title = $j->getSlugTitle();

                return $response->withHeader('Location', ADMIN_URL . "jobs/{$job->id}/{$title}");
            }
        });

        // feature job
        $group->get('/{id}/feature/{action}/{token}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;
            $token = isset($args['token']) ? $args['token']: null;
            $action = isset($args['action']) ? $args['action']: null;

            $j = new Jobs($id);
            $title = $j->getSlugTitle();
            if ($j->featureJob($token, $action)) {
                $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|feature_success', $action));
                return $response->withHeader('Location', ADMIN_URL . "jobs/{$id}/{$title}");
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|feature_error'));
                return $response->withHeader('Location', ADMIN_URL . "jobs/{$id}/{$title}");
            }
        });

        // delete existing job
        $group->get('/{id}/delete/{token}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;
            $token = isset($args['token']) ? $args['token']: null;

            $j = new Jobs($id);
            if ($j->deleteJob($token)) {
                $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|delete_success', $id));
                return $response->withHeader('Location', ADMIN_URL);
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|delete_error', $id));
                return $response->withHeader('Location', ADMIN_MANAGE);
            }
        });

        // activate job
        $group->get('/{id}/activate/{token}', function ($request, $response, $args) use ($app) {

            global $lang;

            $id = isset($args['id']) ? intval($args['id']) : null;
            $token = isset($args['id']) ? $args['token'] : null;

            $j = new Jobs($id);
            if ($j->activateJob($token)) {
                $job = $j->showJobDetails();
                $title = $j->getSlugTitle();

                $notif = new Notifications();
                $notif->sendEmailsToSubscribersMail($id);

                $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|activate_success', $id));
                return $response->withHeader('Location', ADMIN_URL . "jobs/{$job->id}/{$title}");
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|activate_error', $id));
                return $response->withHeader('Location', ADMIN_URL . "jobs/{$id}");
            }
        });

        // deactivate job
        $group->get('/{id}/deactivate/{token}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;
            $token = isset($args['id']) ? $args['token'] : null;

            $j = new Jobs($id);
            if ($j->deactivateJob($token)) {
                $job = $j->showJobDetails();
                $title = $j->getSlugTitle();
                $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|deactivate_success', $id));
                return $response->withHeader('Location',ADMIN_URL . "jobs/{$job->id}/{$title}");
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|deactivate_error', $id));
                return $response->withHeader('Location',ADMIN_URL . "jobs/$id");
            }
        });

        // show job information
        $group->get('/{id}[/{title}]', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;

            $j = new Jobs($id);
            $job = $j->showJobDetails();
            $city = $j->getJobCity($job->city);
            $category = $j->getJobCategory($job->category);
            $applications = $j->countJobApplications();
            if (isset($job) && $job->id) {
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'job.show.php',
                    array(
                        'lang' => $lang,
                        'flash' => $this->get('flash')->getMessages(),
                        'job' => $job,
                        'id' => $id,
                        'applications' => $applications,
                        'category' => $category,
                        'city' => $city
                    ));
            } else {
                $app->getContainer()->get('flash')->addMessage('danger', $lang->t('admin|not_found'));
                return $response->withHeader('Location', ADMIN_MANAGE);
            }
        })->add($mwHelpers['validateUser']);

    })->add($mwHelpers['validateUser']);

    /*
     * Categories group
     * Admin job categories routes
     */
    $group->group('/categories', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        $group->get('[/]', function ($request, $response, $args) use ($app) {
            return $response->withHeader('Location', ADMIN_MANAGE);
        });

        // get category jobs
        $group->get('/{id}[/{name}[/{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? (int)($args['id']) : null;
            $page = isset($args['page']) ?  $args['page']: null; //TODO

            $cat = new Categories($id);
            $start = getPaginationStart($page);
            $count = $cat->countCategoryJobs();
            $number_of_pages = ceil($count / LIMIT);

            $categ = $cat->findCategory();
            $jobs = $cat->findCategoryJobs($start, LIMIT);
            if (isset($categ) && $categ) {
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'categories.php',
                    array(
                        'lang' => $lang,
                        'flash' => $this->get('flash')->getMessages(),
                        'categ' => $categ,
                        'jobs' => $jobs,
                        'id' => $id,
                        'number_of_pages' => $number_of_pages,
                        'current_page' => $page,
                        'page_name' => 'categories'
                    ));
            } else {
                return $response->withHeader('Location', ADMIN_MANAGE);
            }
        });

    })->add($mwHelpers['validateUser']);

    /*
     * Cities group
     * Admin job cities routes
     */
    $group->group('/cities', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        $group->get('[/]', function ($request, $response, $args) use ($app) {
            return $response->withHeader('Location', ADMIN_MANAGE);
        });

        // get category jobs
        $group->get('/{id}/{name}[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;
            $name = isset($args['name']) ?  $args['name']: null;
            $page = isset($args['page']) ? intval($args['page']) : 1;

            $cit = new Cities($id);

            $start = getPaginationStart($page);
            $count = $cit->countCityJobs();
            $number_of_pages = ceil($count / LIMIT);

            $city = $cit->findCity();
            $jobs = $cit->findCityJobs($start, LIMIT);
            if (isset($city) && $city) {
                return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'cities.php',
                    array(
                        'lang' => $lang,
                        'flash' => $this->get('flash')->getMessages(),
                        'city' => $city,
                        'jobs' => $jobs,
                        'id' => $id,
                        'number_of_pages' => $number_of_pages,
                        'current_page' => $page,
                        'page_name' => 'cities'
                    ));
            } else {
                return $response->withHeader('Location', ADMIN_MANAGE);
            }
        });

    })->add($mwHelpers['validateUser']);

    /*
     * Pages group
     * Manage pages
     */
    $group->group('/pages', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        $group->post('[/]', function ($request, $response, $args) use ($app) {

            global $lang;

            $data = $request->getParsedBody();
            $data = escape($data);

            $p = new Pages();
            $p->addToPageList($data);
            $method = (isset($data['id']) && $data['id'] > 0) ? 'edited' : 'added';
            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|page_success', $method));
            return $response->withHeader('Location', ADMIN_URL . 'pages');

        })->add($mwHelpers['isValidReferrer']);

        $group->get('/new', function ($request, $response, $args) use ($app) {

            global $lang;
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'pages.new.php',
                array('lang' => $lang, 'method' => 'new', 'markdown' => ACTIVE,
                    'flash' => $this->get('flash')->getMessages(),
                    'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                    'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                    'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                    'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

        $group->get('/edit/{id}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;

            $p = new Pages();
            $page = $p->showPage($id);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'pages.edit.php',
                array('lang' => $lang, 'page' => $page, 'method' => 'edit', 'markdown' => ACTIVE,
                    'flash' => $this->get('flash')->getMessages(),
                    'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                    'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                    'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                    'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

        $group->get('/delete/{id}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;

            $p = new Pages();
            $p->deleteFromPage($id);
            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|page_delete'));
            return $response->withHeader('Location', ADMIN_URL . 'pages');
        });

        $group->get('[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $page = isset($args['page']) ? $args['page'] : null;

            $p = new Pages();

            $start = getPaginationStart($page);
            $count = $p->countPageList();
            $number_of_pages = ceil($count / LIMIT);

            $pages = $p->showPageList($start, LIMIT);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'pages.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'pages' => $pages,
                    'number_of_pages' => $number_of_pages,
                    'current_page' => $page,
                    'page_name' => 'pages',
                    'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                    'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                    'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                    'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()
        ));

        });

    })->add($mwHelpers['validateUser']);

    /*
     * Blocks group
     * Manage block content
     */
    $group->group('/blocks', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        $group->post('[/]', function ($request, $response, $args)  use ($app) {

            global $lang;

            $data = $request->getParsedBody();
            $data = escape($data);

            $b = new Blocks();
            $b->addToBlockList($data);
            $method = (isset($data['id']) && $data['id'] > 0) ? 'edited' : 'added';
            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|block_success', $method));
            return $response->withHeader('Location', ADMIN_URL . 'blocks');

        })->add($mwHelpers['isValidReferrer']);

        $group->get('/new', function ($request, $response, $args) use ($app) {

            global $lang;
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'blocks.new.php',
                array('lang' => $lang, 'method' => 'new',
                    'flash'=>  $this->get('flash')->getMessages(),
                    'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                    'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                    'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                    'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

        $group->get('/edit/{id}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;

            $b = new Blocks();
            $block = $b->showBlock($id);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'blocks.edit.php',
                array('lang' => $lang, 'block' => $block, 'method' => 'edit',
                'flash'=>  $this->get('flash')->getMessages(),
                'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

        $group->get('/delete/{id}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;

            $b = new Blocks();
            $b->deleteFromBlock($id);
            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|block_delete'));
            return $response->withHeader('Location', ADMIN_URL . 'blocks');
        });

        $group->get('[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $page = isset($args['page']) ? intval($args['page']) : 1;

            $b = new Blocks();

            $start = getPaginationStart($page);
            $count = $b->countBlockList();
            $number_of_pages = ceil($count / LIMIT);

            $blocks = $b->showBlockList($start, LIMIT);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'blocks.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'blocks' => $blocks,
                    'number_of_pages' => $number_of_pages,
                    'current_page' => $page,
                    'page_name' => 'blocks'
                ));

        });

    })->add($mwHelpers['validateUser']);

    /*
     * Banlist group
     * Manage ban list
     */
    $group->group('/ban', function (RouteCollectorProxy $group) use ($app, $mwHelpers) {

        $group->post('[/]', function ($request, $response, $args)  use ($app) {

            global $lang;

            $ban = new Banlist();
            $data = $request->getParsedBody();
            $data = escape($data);
            $ban->addToList($data['type'], $data['value']);

            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|ban_add', $data['value']));
            return $response->withHeader('Location', ADMIN_URL . 'ban');

        })->add($mwHelpers['validateUser']);

        $group->get('/delete/{id}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;

            $ban = new Banlist();
            $value = $ban->deleteFromList($id);

            $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|ban_remove', $value));
            return $response->withHeader('Location', ADMIN_URL . 'ban');
        });

        $group->get('[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $page = isset($args['page']) ? intval($args['page']) : 1;

            $ban = new Banlist();

            $start = getPaginationStart($page);
            $count = $ban->countBanList();
            $number_of_pages = ceil($count / LIMIT);
            $list = $ban->showBanList($start, LIMIT);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'banlist.php',
                array(
                    'lang' => $lang,
                    'list' => $list,
                    'flash' => $this->get('flash')->getMessages(),
                    'number_of_pages' => $number_of_pages,
                    'current_page' => $page,
                    'page_name' => 'banlist',
                    'csrf_key' => $request->getAttribute($this->get('csrf')->getTokenNameKey()),
                    'csrf_keyname' => $this->get('csrf')->getTokenNameKey(),
                    'csrf_token' => $request->getAttribute($this->get('csrf')->getTokenValueKey()),
                    'csrf_tokenname' => $this->get('csrf')->getTokenValueKey()));
        });

    })->add($mwHelpers['validateUser']);

    /*
     * Applications group
     * Admin job applications routes
     */
    $group->group('/applications', function (RouteCollectorProxy $group) use ($app) {

        // show all job applications
        $group->get('[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $page = isset($args['page']) ? intval($args['page']) : 1;

            $a = new Applications();
            $start = getPaginationStart($page);
            $count = $a->countApplications();
            $number_of_pages = ceil($count / LIMIT);

            $applications = $a->getApplications($start);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'applications.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'applications' => $applications,
                    'number_of_pages' => $number_of_pages,
                    'current_page' => $page,
                    'page_name' => 'applications',
                    'count' => $count
                ));
        });

        // get job applications
        $group->get('/jobs/{id}[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($data['id']) ? intval($data['id']) : null;
            $page = isset($data['page']) ? intval($data['page']) : 1;

            $a = new Applications($id);
            $start = getPaginationStart($page);
            $count = $a->countApplications($id);
            $number_of_pages = ceil($count / LIMIT);

            $j = new Jobs($id);
            $title = $j->getSeoTitle();

            $applications = $a->getApplications($start);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'applications.job.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'applications' => $applications,
                    'number_of_pages' => $number_of_pages,
                    'current_page' => $page,
                    'page_name' => 'applications',
                    'count' => $count,
                    'title' => $title,
                    'id' => $id
                ));

        });

    })->add($mwHelpers['validateUser']);

    $group->group('/subscribers', function (RouteCollectorProxy $group) use ($app) {

        $group->get('[/[{page}]]', function ($request, $response, $args) use ($app) {

            global $lang;
            $page = isset($data['page']) ? intval($data['page']) : 1;

            $s = new Subscriptions('');

            $start = getPaginationStart($page);
            $count = $s->countSubscriptions();
            $number_of_pages = ceil($count / LIMIT);

            $users = $s->getAllSubscriptions($start);
            return $this->get('PhpRenderer')->render($response, ADMIN_THEME . 'subscribers.php',
                array(
                    'lang' => $lang,
                    'flash' => $this->get('flash')->getMessages(),
                    'users' => $users,
                    'number_of_pages' => $number_of_pages,
                    'current_page' => $page,
                    'count' => $count,
                    'page_name' => 'subscribers'
                ));
        });

        $group->get('/{id}/{action}/{token}', function ($request, $response, $args) use ($app) {

            global $lang;
            $id = isset($args['id']) ? intval($args['id']) : null;
            $action = isset($args['action']) ? $args['action']: null;
            $token = isset($args['token']) ? $args['token']: null;
            $category = null;

            $s = new Subscriptions('');
            $user = $s->getUserSubscription($id, $token);

            if (isset($user)) {
                switch ($action) {
                    case 'approve':
                        $s->updateSubscription($id, ACTIVE);
                        $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|subscribe_confirm'));
                        break;
                    case 'deactivate':
                        $s->updateSubscription($id, INACTIVE);
                        $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|subscribe_deactivate'));
                        break;
                    case 'delete':
                        $s->deleteSubscription($id, $token);
                        $app->getContainer()->get('flash')->addMessage('success', $lang->t('admin|subscribe_delete'));
                        break;
                }
            }
            return $response->withHeader('Location', ADMIN_URL . 'subscribers');
        });
    })->add($mwHelpers['validateUser']);
});