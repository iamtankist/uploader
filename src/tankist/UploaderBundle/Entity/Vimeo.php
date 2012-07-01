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
     * @var string $status
     *
     * @ORM\Column(name="status", type="string", length=255)
     */
    private $status;

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
     * Set status
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Get status
     *
     * @return string 
     */
    public function getStatus()
    {
        return $this->status;
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

    public function upload($service){
        $video_id = $service->uploadAndSetTitle($this);
        return $video_id;
    }
}