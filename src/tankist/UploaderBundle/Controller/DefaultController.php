<?php

namespace tankist\UploaderBundle\Controller;
use tankist\UploaderBundle\Entity\Vimeo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="_landing")
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @Route("/list/", name="_list")
     * @Template()
     */
    public function listAction()
    {
        $excludeFiles = array('.','..','.DS_Store','@eaDir');

        $dir = $this->container->getParameter('video_directory');
		$dh  = opendir($dir);

        $vimeoRepository = $this->getDoctrine()->getRepository('tankistUploaderBundle:Vimeo');

        $files = array();
		while (false !== ($filename = readdir($dh))) {
            if(!in_array($filename,$excludeFiles)){
                $vimeoVideo = $vimeoRepository->findOneByFilename($filename);
		        $files[] = array(
                    'name' => $filename,
                    'vimeo'    => $vimeoVideo ? $vimeoVideo->getVimeoId() : 0
                );
            }
		}

        usort($files, function($a,$b){
            if ($a['name'] == $b['name']) {
                return 0;
            } else {
                return $a['name'] < $b['name'] ? 1 : -1; // reverse order
            }
        });

		return new Response(json_encode(array('files' => $files)));
    }

    /**
     * @Route("/feed/")
     * @Template()
     */
    public function feedAction()
    {
        $client = new \Zend_Http_Client('http://vimeo.com/api/v2/mkrtchyan/videos.json');
        $response = $client->request();

        $data = json_decode($response->getBody());
        var_dump($data);
        return new Response("");
    }

    /**
     * @Route("/upload", name="_upload")
     * @Template()
     */
    public function uploadAction()
    {
        $filename = $this->getRequest()->query->get('filename');
        $rootDir = $this->get('kernel')->getRootDir();
        $cacheDir = $this->get('kernel')->getCacheDir();
        $logDir = $this->get('kernel')->getLogDir();

        $videoDir = $this->container->getParameter('video_directory');
        
        $filePath = $videoDir."/".$filename;
        
        $consoleBin = $rootDir."/console";
        $cmd = "$consoleBin vimeo:upload \"$filePath\"";

        $outputfile = $logDir."/output_$filename.out";
        $pidfile = $cacheDir."/pid_$filename.pid";


        $fullCmd = sprintf('%s < /dev/null > "%s" 2>&1 & echo $! >> "%s"', $cmd, $outputfile, $pidfile);

        echo "out => $outputfile<br>";
        echo "pid => $pidfile<br>";
        echo "cmd => $fullCmd<br>";
        exec($fullCmd);
        exit;
    }

    /**
     * @Route("/info/", name="_info")
     * @Template()
     */
    public function infoAction()
    {
        $filename = $this->getRequest()->query->get('filename');

        
        $cosumerKey = $this->container->getParameter('vimeo_consumer_key');
        $cosumerSecret = $this->container->getParameter('vimeo_consumer_secret');
        $accessToken = $this->container->getParameter('vimeo_access_token');
        $accessTokenSecret = $this->container->getParameter('vimeo_access_token_secret');
        

        $vimeo = new \Vimeo_Vimeo($cosumerKey, $cosumerSecret, $accessToken, $accessTokenSecret,null);

        $video = $this->getDoctrine()
                            ->getRepository('tankistUploaderBundle:Vimeo')
                            ->findOneByFilename($filename);

        if($video){
            return new Response(json_encode(array('status'=>'success','id'=>$video->getVimeoId())));
        } else {


            $searchQuery = pathinfo($filename,PATHINFO_FILENAME);

            try {
                // 2010-11-29 182354
                $response = $vimeo->call('vimeo.videos.search', array(
                    'user_id'          => 'mkrtchyan',
                    'page'             => 1,
                    'per_page'         => 1,
                    'summary_response' => 0,
                    'full_response'    => 0,
                    'query'            => $searchQuery,
                    'sort'             => 'relevant'
                ));

                if(!empty($response->videos->video)){
                    $vimeoId = $response->videos->video[0]->id;
                    
                    $video = new Vimeo();
                    $video->setFilename($filename);
                    $video->setVimeoId($vimeoId);
                    $em = $this->getDoctrine()->getEntityManager();
                    $em->persist($video);
                    $em->flush();
                    return new Response(json_encode(array('status'=>'success','id'=>$video->getVimeoId())));
                } else {
                    return new Response(json_encode(array('status'=>'error','message'=>'video not found on vimeo')));
                }
            }
            catch (VimeoAPIException $e) {

                return new Response(json_encode(array('status'=>'error','message'=>"Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}")));

            }
        }

        return new Response("");
    }
}
