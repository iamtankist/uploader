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
                    'vimeo' => array(
                        'id'        => $vimeoVideo ? $vimeoVideo->getVimeoId() : 0,
                        'status'    => $vimeoVideo ? $vimeoVideo->getStatus()  : 'not uploaded',
                    )
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

}
