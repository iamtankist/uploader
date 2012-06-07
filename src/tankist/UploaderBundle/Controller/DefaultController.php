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
     * @Route("/hello/{name}", name="_hello")
     * @Template()
     */
    public function indexAction($name)
    {
        return array('name' => $name);
    }

    /**
     * @Route("/list/", name="_list")
     * @Template()
     */
    public function listAction()
    {
        $excludeFiles = array('.','..');

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
     * @Route("/upload/")
     * @Template()
     */
    public function uploadAction()
    {
        //$vimeo = new phpVimeo('CONSUMER_KEY', 'CONSUMER_SECRET', 'ACCESS_TOKEN', 'ACCESS_TOKEN_SECRET');
        $vimeo = new \Vimeo_Vimeo('f583c70f09eb0fc03172d51384fc0d85', 'e475e4242f51ffa', '6078495ac877f9e395c98d855bcba7bd', 'b4e1334cae81378e03cf9117ccb6159f2cd25dbe');

        try {
            $video_id = $vimeo->upload("/Users/tankist/Movies/Videos/2012-05-22 160802.mp4",true,'/tmp');

            if ($video_id) {
                echo '<a href="http://vimeo.com/' . $video_id . '">Upload successful!</a>';

                $vimeo->call('vimeo.videos.setPrivacy', array('privacy' => 'nobody', 'video_id' => $video_id));
                $vimeo->call('vimeo.videos.setTitle', array('title' => 'YOUR TITLE', 'video_id' => $video_id));
                $vimeo->call('vimeo.videos.setDescription', array('description' => 'YOUR_DESCRIPTION', 'video_id' => $video_id));
            }
            else {
                echo "Video file did not exist!";
            }
        }
        catch (VimeoAPIException $e) {
            echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
        }
        return new Response("");
    }

    /**
         * @Route("/info/", name="_info")
         * @Template()
         */
        public function infoAction()
        {
            $filename = $this->getRequest()->query->get('filename');
            //$vimeo = new phpVimeo('CONSUMER_KEY', 'CONSUMER_SECRET', 'ACCESS_TOKEN', 'ACCESS_TOKEN_SECRET');
            $vimeo = new \Vimeo_Vimeo('f583c70f09eb0fc03172d51384fc0d85', 'e475e4242f51ffa', '6078495ac877f9e395c98d855bcba7bd', 'b4e1334cae81378e03cf9117ccb6159f2cd25dbe');

            $video = $this->getDoctrine()
                                ->getRepository('tankistUploaderBundle:Vimeo')
                                ->findOneByFilename($filename);

            if($video){
                return new Response(json_encode(array('status'=>'success','id'=>$video->getVimeoId())));
            } else {

                try {
                    // 2010-11-29 182354
                    $response = $vimeo->call('vimeo.videos.search', array(
                        'user_id'          => 'mkrtchyan',
                        'page'             => 1,
                        'per_page'         => 1,
                        'summary_response' => 0,
                        'full_response'    => 0,
                        'query'            => $filename,
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
