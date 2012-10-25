<?php
namespace tankist\UploaderBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use tankist\UploaderBundle\Entity\Vimeo;

class NotifyGtalkCommand extends ContainerAwareCommand
{

    protected $client;
    protected function configure()
    {
        $this
            ->setName('notify:gtalk')
            ->setDescription('Unlocks file upload services')
            ->addArgument(
                'msg',
                InputArgument::REQUIRED,
                'message to send'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $msg = $input->getArgument("msg");

        require_once(__DIR__.'/../../../../vendor/JAXL/jaxl.php');
            $this->client = new \JAXL(array(
            'jid' => $this->getContainer()->getParameter('jabber_login'),
            'pass' => $this->getContainer()->getParameter('jabber_password'),
            //'force_tls' => true,
            'auth_type' => 'PLAIN',
            'resource' => 'Diskstation',
            //'log_level' => JAXL_DEBUG
        ));

        //
        // add necessary event callbacks here
        //
        $client = $this->client;
        $this->client->add_cb('on_auth_success', function() use ($client, $msg) {
            _info("got on_auth_success cb, jid ".$client->full_jid->to_string());
            $client->set_status("Available", "available", 10);
            sleep(1);

            $msgStanza = new \XMPPMsg(array(
                'from'=>$client->full_jid->to_string(),
                'to'=>'tankist@gmail.com'), $msg);
            $client->send($msgStanza);
            $client->send_end_stream();
        });

        $this->client->add_cb('on_auth_failure', function($reason) use ($client)  {
            $client->send_end_stream();
            _info("got on_auth_failure cb with reason $reason");
        });

        $this->client->add_cb('on_chat_message', function($stanza) use ($client)  {
            // echo back incoming message stanza
            $stanza->to = $stanza->from;
            $stanza->from = $client->full_jid->to_string();
            $client->send($stanza);
            $client->send_end_stream();
        });

        $this->client->add_cb('on_disconnect', function() {
            _info("got on_disconnect cb");
        });

        //
        // finally start configured xmpp stream
        //
        $this->client->start();
        echo "done\n";
    }

}