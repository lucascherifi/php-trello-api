<?php

namespace Trello;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Trello\Exception\InvalidArgumentException;

class Service extends Manager
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * Constructor.
     *
     * @param ClientInterface               $client
     * @param EventDispatcherInterface|null $dispatcher
     */
    public function __construct(ClientInterface $client, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($client);

        $this->dispatcher = $dispatcher ? $dispatcher : new EventDispatcher();
    }

    /**
     * Get event dispatcher
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Attach an event listener
     *
     * @param string   $eventName @see Events for name constants
     * @param callable $listener  The listener
     * @param int      $priority  The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * Attach an event subscriber
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    /**
     * Checks whether a given request is a Trello webhook
     *
     * @param Request $request
     *
     * @return bool
     */
    public function isTrelloWebhook(Request $request)
    {
        if (!$request->getMethod() === 'POST') {
            return false;
        }

        if (!$request->headers->has('X-Trello-Webhook')) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether a given request is a Trello webhook
     * and raises appropriate events @see Events
     *
     * @param Request|null $request
     */
    public function handleWebhook(Request $request = null)
    {
        if (!$request) {
            $request = Request::createFromGlobals();
        }

        if (!$this->isTrelloWebhook($request) || !$action = $request->get('action')) {
            return;
        }

        if (!isset($action['type'])) {
            throw new InvalidArgumentException('Unable to determine event from request.');
        }

        if (!isset($action['data'])) {
            throw new InvalidArgumentException('Unable to retrieve data from request.');
        }

        $eventName = $action['type'];
        $data      = $action['data'];

        switch ($eventName) {
            case Events::CARD_UPDATE:
                $event = new Event\CardEvent();
                $event->setCard($this->getCard($data['card']['id']));
                break;
            case Events::CARD_ADD_MEMBER:
                $event = new Event\CardMemberEvent();
                $event->setCard($this->getCard($data['card']['id']));
                $event->setMember($this->getMember($data['idMember']));
                break;
            default:
                $event = null;
        }

        if (null !== $event) {
            $event->setRequestData($data);
        }

        $this->dispatcher->dispatch($eventName, $event);
    }
}
