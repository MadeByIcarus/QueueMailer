<?php

namespace Icarus\QueueMailer;


use Doctrine\ORM\EntityManager;
use Icarus\QueueMailer\Exceptions\EmailTemplateNotFoundException;
use Icarus\QueueMailer\Model\Email;
use Icarus\QueueMailer\Model\EmailTemplate;
use Latte\Loaders\StringLoader;
use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\Bridges\ApplicationLatte\UIMacros;
use Nette\InvalidArgumentException;
use Nette\Localization\ITranslator;
use Nette\Utils\Validators;


class QueueMailer
{

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var string
     * Default sender
     */
    private $sender;

    /**
     * @var string
     * Default language
     */
    private $defaultLanguage;

    /**
     * @var ITranslator
     */
    private $translator;

    /**
     * @var ILatteFactory
     */
    private $latteFactory;

    /**
     * @var LinkGenerator
     */
    private $linkGenerator;



    function __construct($defaultSender, $defaultLanguage, EntityManager $entityManager, ITranslator $translator, ILatteFactory $latteFactory, LinkGenerator $linkGenerator)
    {
        $this->sender = $defaultSender;
        $this->defaultLanguage = $defaultLanguage;
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->latteFactory = $latteFactory;
        $this->linkGenerator = $linkGenerator;
    }



    public function send(Email $email)
    {
        $this->entityManager->persist($email);
    }



    public function prepareEmailFromTemplate($templateName, $to, array $parameters, $language = null)
    {
        if (!$language) {
            $language = $this->defaultLanguage;
        }

        $repository = $this->entityManager->getRepository(EmailTemplate::class);

        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $repository->findOneBy(['name' => $templateName, 'language' => $language]);

        if (!$emailTemplate) {
            throw new EmailTemplateNotFoundException("Template $templateName:$language not found in the database.");
        }

        $latte = $this->latteFactory->create();
        $latte->addProvider("uiControl", $this->linkGenerator);
        UIMacros::install($latte->getCompiler());
        $latte->setLoader(new StringLoader());

        $body = $latte->renderToString($emailTemplate->getBody(), $parameters);

        return $this->prepareEmail($to, $emailTemplate->getSubject(), $body, $emailTemplate->getFrom() ?: null);
    }


    
    public function prepareEmail($to, $subject, $body, $from = null)
    {
        if (!Validators::isEmail($to)) {
            throw new InvalidArgumentException("Invalid Email address '$to'");
        }

        if ($from && !Validators::isEmail($from)) {
            throw new InvalidArgumentException("Invalid Email address '$from'");
        }

        $email = new Email();
        $email->setSender($from ?: $this->sender);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setBody($body);

        return $email;
    }
}