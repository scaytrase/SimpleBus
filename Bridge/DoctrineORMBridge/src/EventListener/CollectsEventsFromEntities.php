<?php

namespace SimpleBus\DoctrineORMBridge\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use SimpleBus\Message\Recorder\ContainsRecordedMessages;

class CollectsEventsFromEntities implements EventSubscriber, ContainsRecordedMessages
{
    private $collectedEvents = array();

    public function getSubscribedEvents()
    {
        return array(
            Events::preFlush,
            Events::postFlush
        );
    }

    public function preFlush(PreFlushEventArgs $eventArgs)
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getIdentityMap() as $entities) {
            foreach ($entities as $entity){
                $this->collectEventsFromEntity($entity);
            }
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collectEventsFromEntity($entity);
        }
    }

    /**
     * We need to listen on postFlush for Lifecycle Events
     * All Lifecycle callback events are triggered after the onFlush event
     *
     * @param PostFlushEventArgs $eventArgs
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getIdentityMap() as $entities) {
            foreach ($entities as $entity){
                $this->collectEventsFromEntity($entity);
            }
        }
    }

    public function recordedMessages()
    {
        return $this->collectedEvents;
    }

    public function eraseMessages()
    {
        $this->collectedEvents = array();
    }

    private function collectEventsFromEntity($entity)
    {
        if ($entity instanceof ContainsRecordedMessages
            && ( !$entity instanceof Proxy
                || ($entity instanceof Proxy && $entity->__isInitialized__)
            )
        ) {
            foreach ($entity->recordedMessages() as $event) {
                $this->collectedEvents[] = $event;
            }
            $entity->eraseMessages();
        }
    }
}
