<?php

namespace tankist\UploaderBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * tankist\UploaderBundle\Entity\Vimeo
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="tankist\UploaderBundle\Entity\VimeoRepository")
 */
class Vimeo
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string $filename
     *
     * @ORM\Column(name="filename", type="string", length=255)
     */
    private $filename;

    /**
     * @var integer $vimeo_id
     *
     * @ORM\Column(name="vimeo_id", type="integer")
     */
    private $vimeo_id;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set filename
     *
     * @param string $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Get filename
     *
     * @return string 
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set vimeo_id
     *
     * @param integer $vimeoId
     */
    public function setVimeoId($vimeoId)
    {
        $this->vimeo_id = $vimeoId;
    }

    /**
     * Get vimeo_id
     *
     * @return integer 
     */
    public function getVimeoId()
    {
        return $this->vimeo_id;
    }

    public function upload($tmpDir,$logger){
        //$vimeo = new phpVimeo('CONSUMER_KEY', 'CONSUMER_SECRET', 'ACCESS_TOKEN', 'ACCESS_TOKEN_SECRET');
        $vimeo = new \Vimeo_Vimeo('f583c70f09eb0fc03172d51384fc0d85', 'e475e4242f51ffa', '6078495ac877f9e395c98d855bcba7bd', 'b4e1334cae81378e03cf9117ccb6159f2cd25dbe',$logger);
        $video_id = $vimeo->upload($this->getFilename(), true, $tmpDir,2*1024*1024);
        //$video_id = $vimeo->upload($this->getFilename(), true, $tmpDir, 512*1024);

        if ($video_id) {
            

            $vimeo->call('vimeo.videos.setPrivacy', array('privacy' => 'nobody', 'video_id' => $video_id));
            $vimeo->call('vimeo.videos.setTitle', array('title' => pathinfo($this->getFilename(),PATHINFO_BASENAME), 'video_id' => $video_id));
            //$vimeo->call('vimeo.videos.setDescription', array('description' => 'YOUR_DESCRIPTION', 'video_id' => $video_id));
        }

        return $video_id;
    }
}