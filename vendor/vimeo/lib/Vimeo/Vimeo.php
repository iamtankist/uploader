<?php
# vendor/vimeo/lib/Vimeo/Vimeo.php

require_once __DIR__.'/src/vimeo.php';

class Vimeo_Vimeo extends phpVimeo {

	public function __construct($consumer_key, $consumer_secret, $token = null, $token_secret = null)
    {
        parent::__construct($consumer_key, $consumer_secret, $token, $token_secret);
    }

    public function setLogger($logger){
    	$this->logger = $logger;
    }


    public function setTmpDir($tmpDir){
    	$this->tmpDir = $tmpDir;
    }

	public function uploadAndSetTitle($video){
		$video_id = parent::upload($video->getFilename(), true, $this->tmpDir,2*1024*1024);
        //$video_id = $vimeo->upload($this->getFilename(), true, $tmpDir, 512*1024);

        if ($video_id) {
            $this->call('vimeo.videos.setPrivacy', array('privacy' => 'nobody', 'video_id' => $video_id));
            $this->call('vimeo.videos.setTitle', array('title' => pathinfo($video->getFilename(),PATHINFO_BASENAME), 'video_id' => $video_id));
            //$vimeo->call('vimeo.videos.setDescription', array('description' => 'YOUR_DESCRIPTION', 'video_id' => $video_id));
        }

        return $video_id;
	}


	/**
     * Upload a video in one piece.
     *
     * @param string $file_path The full path to the file
     * @param boolean $use_multiple_chunks Whether or not to split the file up into smaller chunks
     * @param string $chunk_temp_dir The directory to store the chunks in
     * @param int $size The size of each chunk in bytes (defaults to 2MB)
     * @return int The video ID
     */
    public function upload($file_path, $use_multiple_chunks = false, $chunk_temp_dir = '.', $size = 2097152, $replace_id = null)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        // Figure out the filename and full size
        $path_parts = pathinfo($file_path);
        $file_name = $path_parts['basename'];
        $file_size = filesize($file_path);

        $this->logger->addInfo('Starting upload, filesize: '.$file_size);

        // Make sure we have enough room left in the user's quota
        $quota = $this->call('vimeo.videos.upload.getQuota');
        $this->logger->addInfo('Checking Quota, free: '.$quota->user->upload_space->free);
        if ($quota->user->upload_space->free < $file_size) {
            $this->logger->addError('The file is larger than the user\'s remaining quota.');
            throw new VimeoAPIException('The file is larger than the user\'s remaining quota.', 707);
        }

        // Get an upload ticket
        $params = array();

        if ($replace_id) {
            $params['video_id'] = $replace_id;
        }

        $rsp = $this->call('vimeo.videos.upload.getTicket', $params, 'GET', self::API_REST_URL, false);
        $ticket = $rsp->ticket->id;
        $endpoint = $rsp->ticket->endpoint;

        // Make sure we're allowed to upload this size file
        if ($file_size > $rsp->ticket->max_file_size) {
            $this->logger->addError('File exceeds maximum allowed size.');
            throw new VimeoAPIException('File exceeds maximum allowed size.', 710);
        }

        // Split up the file if using multiple pieces
        $chunks = array();
        if ($use_multiple_chunks) {
            if (!is_writeable($chunk_temp_dir)) {
                $this->logger->addError('Could not write chunks. Make sure the specified folder has write access.');
                throw new Exception('Could not write chunks. Make sure the specified folder has write access.');
            }

            // Create pieces
            $number_of_chunks = ceil(filesize($file_path) / $size);
            for ($i = 0; $i < $number_of_chunks; $i++) {
                $chunk_file_name = "{$chunk_temp_dir}/{$file_name}.{$i}";

                // Break it up
                $chunk = file_get_contents($file_path, FILE_BINARY, null, $i * $size, $size);
                $file = file_put_contents($chunk_file_name, $chunk);

                $chunks[] = array(
                    'file' => realpath($chunk_file_name),
                    'size' => filesize($chunk_file_name)
                );
            }
        } else {
            $chunks[] = array(
                'file' => realpath($file_path),
                'size' => filesize($file_path)
            );
        }

        // Upload each piece
        foreach ($chunks as $i => $chunk) {
            $params = array(
                'oauth_consumer_key'     => $this->_consumer_key,
                'oauth_token'            => $this->_token,
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp'        => time(),
                'oauth_nonce'            => $this->_generateNonce(),
                'oauth_version'          => '1.0',
                'ticket_id'              => $ticket,
                'chunk_id'               => $i
            );

            // Generate the OAuth signature
            $params = array_merge($params, array(
                'oauth_signature' => $this->_generateSignature($params, 'POST', self::API_REST_URL),
                'file_data'       => '@'.$chunk['file'] // don't include the file in the signature
            ));


            // Post the file
            $curl = curl_init($endpoint);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            $rsp = curl_exec($curl);
            curl_close($curl);

            $this->logger->addInfo('PROGRESS: '.($i+1).' of '.count($chunks));
        }

        // Verify
        $verify = $this->call('vimeo.videos.upload.verifyChunks', array('ticket_id' => $ticket));
        $this->logger->addDebug('VERIFICATION: '.var_export($verify,true));


        if(isset($verify->ticket->chunks->chunk['id'])) {
            $chunk_id = $verify->ticket->chunks->chunk['id'];
            $chunk_size = $verify->ticket->chunks->chunk['size'];
            $this->verifyChunk($chunks, $chunk_id, $chunk_size);
        } else {
            foreach ($verify->ticket->chunks->chunk as $chunk_check) {
                $this->verifyChunk($chunks, $chunk_check->id, $chunk_check->size);
            }
        }

        // Complete the upload
        $complete = $this->call('vimeo.videos.upload.complete', array(
            'filename' => $file_name,
            'ticket_id' => $ticket
        ));

        // Clean up
        if (count($chunks) > 1) {
            foreach ($chunks as $chunk) {
                unlink($chunk['file']);
            }
        }

        // Confirmation successful, return video id
        if ($complete->stat == 'ok') {
             $this->logger->addINFO('COMPLETE');
            return $complete->ticket->video_id;
        }
        else if ($complete->err) {
            $this->logger->addError($complete->err->msg);
            throw new VimeoAPIException($complete->err->msg, $complete->err->code);
        }
    }

    protected function verifyChunk($chunks, $chunk_id, $chunk_size){
        $chunk = $chunks[$chunk_id];

        if ($chunk['size'] != $chunk_size) {
            // size incorrect, uh oh
            $errMsg = "Chunk {$chunk_id} is actually {$chunk['size']} but uploaded as {$chunk_size}";
            $this->logger->addError($errMsg);
            echo $errMsg;
        }   
    }

}