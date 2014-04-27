<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

function get_Size($file) {

    $size = 0;

    try {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
        else {
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file->getRealpath())) as $f) {
                $size += $f->getSize();
            }
        }
    } catch (RuntimeException $e) {}

    return $size;
}

$app->get("/api/directory/content/{dirpath}", function (Request $request, $dirpath) use ($app) {

    $dirContent = array();

    $finder = new Finder();
    $finder->followLinks()
            ->depth('< 1')
            ->in("{$app['cakebox.root']}{$dirpath}")
            ->ignoreVCS(true)
            ->ignoreDotFiles($app['directory.ignoreDotFiles'])
            ->notName($app["directory.ignore"])
            ->sortByType();

    foreach ($finder as $file) {

        if ($file->isLink()) {
            $linkTo = readlink("{$app['cakebox.root']}{$dirpath}{$file->getBasename()}");
            if (file_exists($linkTo) == false)
                continue;

            $file = new SplFileInfo($linkTo);
        }

        $pathInfo = array();
        $pathInfo["name"] = $file->getBasename();
        $pathInfo["type"] = $file->getType();
        $pathInfo["ctime"] = $file->getCTime();
        $pathInfo["size"] = get_Size($file);
        $pathInfo["access"] = "{$app['cakebox.access']}{$dirpath}{$file->getBasename()}";

        $pathInfo["extraType"] = false;
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $app["extension.video"]))
            $pathInfo["extraType"] = "video";
        else if (in_array($ext, $app["extension.audio"]))
            $pathInfo["extraType"] = "audio";
        else if (in_array($ext, $app["extension.image"]))
            $pathInfo["extraType"] = "image";
        else if (in_array($ext, $app["extension.archive"]))
            $pathInfo["extraType"] = "archive";
        else if (in_array($ext, $app["extension.subtitle"]))
            $pathInfo["extraType"] = "subtitle";

        array_push($dirContent, $pathInfo);
    }

    return $app->json($dirContent);
})
->value("dirpath", "")
->assert("dirpath", ".*");

$app->get("/api/directory/download/{dirpath}", function (Request $request, $dirpath) use ($app) {

    $dirname = pathinfo($dirpath)["basename"];

    $p = new PharData("{$app['cakebox.root']}{$dirpath}/../{$dirname}.tar");
    $p->compress(Phar::NONE);
    $p->buildFromDirectory("{$app['cakebox.root']}{$dirpath}");

    return $app->json("OK");
})
->value("dirpath", "")
->assert("dirpath", ".*");
