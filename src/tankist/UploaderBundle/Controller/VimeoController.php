<?php

namespace tankist\UploaderBundle\Controller;
use tankist\UploaderBundle\Entity\Vimeo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class VimeoController extends Controller
{
    /**
     * @Route("/vimeo/feed/")
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
     * @Route("/vimeo/upload", name="_upload")
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
        $cmd = "$consoleBin upload:vimeo \"$filePath\"";

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
     * @Route("/vimeo/progress/", name="_progress")
     * @Template()
     */
    public function progressAction()
    {
        $filename = $this->getRequest()->query->get('filename');

        $logDir = $this->get('kernel')->getLogDir();
        $logFile = $logDir."/log_".$filename.".log";

        if(!file_exists($logFile)){
            return new Response(json_encode(array('status'=>'error','message'=>"Log file does not exist")));
        }

        $lines = file($logFile);

        $lastMilestone = 0;
        $totalMilestones = 0;
        foreach ($lines as $line_num => $line) {
            if(preg_match('/COMPLETE/',$line)){
                return new Response(json_encode(array('status'=>'complete','message'=>"Upload was complete successfullty")));
            }

            if(preg_match('/VERIFICATION/',$line)){
                return new Response(json_encode(array('status'=>'verification','message'=>"File upload is being verified")));
            }

            if(preg_match('/PROGRESS: (\d+) of (\d+)/',$line, $matches)){
                $lastMilestone = (int)$matches[1];
                $totalMilestones = (int)$matches[2];
            }
        }

        if($totalMilestones) {
            $percent = round($lastMilestone/$totalMilestones*100);
        } else {
            return new Response(json_encode(array('status'=>'error','message'=>"File is not being uploaded")));
        }

        return new Response(json_encode(array('status'=>'progress','message'=>"File is being uploaded",'percent' => $percent)));
    }

    /**
     * @Route("/vimeo/info/", name="_info")
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
                    $video->setStatus("uploaded");
                    $em = $this->getDoctrine()->getEntityManager();
                    $em->persist($video);
                    $em->flush();
                    return new Response(json_encode(array('status'=>'success','id'=>$video->getVimeoId(),'status' => $video->getStatus())));
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
