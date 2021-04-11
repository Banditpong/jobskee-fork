<?php
/**
 * Jobskee - open source job board
 *
 * @author      Elinore Tenorio <elinore.tenorio@gmail.com>
 * @license     MIT
 * @url         http://www.jobskee.com
 *
 * A list of generic functions used around Jobskee
 */

function isValidImageExt($image)
{
    $ext = array('jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG', 'gif', 'GIF');
    if (in_array($image, $ext)) {
        return true;
    }
    return false;
}

/*
 * converts spaces to dash and makes the string URL friendly
 */
function slugify($string)
{
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = strtolower($string);
    $string = preg_replace("/\W/", "-", $string);
    $string = preg_replace("/-+/", '-', $string);
    $string = trim($string, '-');
    return $string;
}

/*
 * removes unnecessary spaces
 */
function clean($string)
{
    $string = preg_replace("/\W\s+/", ' ', $string);
    $string = preg_replace("/-+/", ' ', $string);
    $string = trim($string, '');
    return $string;
}

/*
 * return an excerpt text with X number of characters
 */
function excerpt($text, $count = 160)
{
    if (strlen($text) > $count) {
        $text = substr($text, 0, $count);
        if (strrpos($text, ' ') !== false) {
            $text = substr($text, 0, strrpos($text, ' '));
        }
        $text = $text . '...';
    }
    return $text;
}

/*
 * escape output
 */
function _e($string, $method = null)
{
    if (isset($method) && $method != 'input') {
        echo nl2br(htmlspecialchars(trim($string)));
    } else {
        echo htmlspecialchars(trim($string));
    }
}

/*
* http://stackoverflow.com/questions/10276656/php-errors-parsing-xml-rss-feed
*/
function escapeXML($string)
{
    return str_replace(array("&amp;", "&"), array("&", "&amp;"), $string);
}

function niceDate($date)
{
    echo utf8_encode(strftime('%d %b %Y', strtotime($date)));
}

function token()
{
    return md5(uniqid() . time());
}

function accessToken($id)
{
    $j = new Jobs($id);
    $job = $j->showJobDetails();
    return sha1($job->id . $job->created);
}

/*
 * strip HTML tags from input - could be used for a string or array
 */
function escape($raw)
{
    if (is_array($raw)) {
        foreach ($raw as $k => $v) {
            $data[$k] = strip_tags($v);
        }
    } else {
        $data = strip_tags($raw);
    }
    return $data;
}

/*
 * allow job posting if ALLOW_JOB_POST = 1
 */
function isJobPostAllowed(Slim\Psr7\Request $request, Slim\Routing\Route $route)
{
    global $lang, $app;

    $container = $app->getContainer();

    if (ALLOW_JOB_POST != 1 && (!isset($_SESSION['email']) || !$_SESSION['email'])) {
        $container->get('flash')->addMessage('danger', 'Job posting is not allowed.');
        return $app->getResponseFactory()->createResponse()->withHeader('Location', BASE_URL);
    }

    $response = $route->handle($request);
    return $response;
}

;

/*
 * protects admin pages by first authenticating the user
 */
function validateUser($request, $route)
{
    global $lang, $app;

    if (!isset($_SESSION['email']) || !$_SESSION['email']) {
        $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|login_needed'));
        return $app->getResponseFactory()->createResponse()->withHeader('Location', LOGIN_URL);
    } else {
        $admin = R::findOne('admin', ' email=:email ', array(':email' => $_SESSION['email']));
        if (!$admin->id) {
            $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|invalid_login'));
            return $app->getResponseFactory()->createResponse()->withHeader('Location', LOGIN_URL);
        }
    }

    $response = $route->handle($request);//this goes back to the route.
    return $response;
}

/*
 * returns true if the user logged in is a valid user
 */
function userIsValid()
{
    if (!isset($_SESSION['email']) || !$_SESSION['email']) {
        return false;
    } else {
        $admin = R::findOne('admin', ' email=:email ', array(':email' => $_SESSION['email']));
        if (isset($admin) && $admin->id) {
            return true;
        }
        return false;
    }
}

/*
 * checks whether IP address is in the ban list
 */
function isBanned($request, $route)
{
    global $lang, $app;
    $ban = R::findOne('banlist', ' value=:value ', array(':value' => $_SERVER['REMOTE_ADDR']));
    if ($ban && $ban->id) {
        $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|ip_banned', $_SERVER['REMOTE_ADDR']));
        return $app->getResponseFactory()->createResponse()->withHeader('Location', BASE_URL);;
    }

    $response = $route->handle($request);//this goes back to the route.
    return $response;
}

/*
 * checks whether the referrer is the site itself
 */
function isValidReferrer($request, $route)
{
    global $lang, $app;

    if (stripos($request->getHeader("HTTP_REFERER")[0], BASE_URL, 0) === false) {
        $app->getContainer()->get('flash')->addMessage('danger', $lang->t('alert|operation_not_allowed'));
        return $app->getResponseFactory()->createResponse()->withHeader('Location', BASE_URL);
    }

    $response = $route->handle($request);
    return $response;
}

/*
 * get pagination start
 */
function getPaginationStart($page = null)
{
    return (!$page || $page == 1) ? 0 : ((($page - 1) * LIMIT));
}

function splitTerms($terms)
{
    $words = preg_split("/[\s,]+/", $terms);
    $words = array_filter($words);
    $words = preg_replace('/([\$\.\|\+\*\?\!\/\\\ \,\[\]\(\)\{\}\|\<])/', "\\\\$1", $words);
    return $words;
}

/*
 * check if URL is valid
 */
function checkURL($url)
{
    $valid = filter_var($url, FILTER_VALIDATE_URL);
    return $valid;
}

/*
 * cURL get function
 */
function curlGet($url)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)'
    ));
    $resp = curl_exec($curl);
    curl_close($curl);
}

$mwHelpers['isJobPostAllowed'] = function ($request, $route) {
    return isJobPostAllowed($request, $route);
};
$mwHelpers['validateUser'] = function ($request, $route) {
    return validateUser($request, $route);
};
$mwHelpers['isValidReferrer'] = function ($request, $route) {
    return isValidReferrer($request, $route);
};
$mwHelpers['isBanned'] = function ($request, $route) {
    return isBanned($request, $route);
};