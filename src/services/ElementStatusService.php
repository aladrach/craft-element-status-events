<?php
namespace putyourlightson\elementstatusevents\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\events\ElementEvent;
use craft\services\Elements;
use craft\web\Response;
use putyourlightson\elementstatusevents\behaviors\ElementStatusBehavior;
use putyourlightson\elementstatusevents\events\ElementStatusesEvent;
use yii\base\Event;

class ElementStatusService extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event ElementStatusesEvent
     */
    const EVENT_ELEMENT_STATUSES_CHANGED = 'elementStatusesChanged';

    // Properties
    // =========================================================================

    /**
     * @var Element[]
     */
    private $_elementsChanged = [];

    // Public Methods
    // =========================================================================

    /**
     * Registers event listeners
     */
    public function registerEventListeners()
    {
        parent::init();

        // Before saving an element
        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT, function(ElementEvent $event) {
            /** @var Element $element */
            $element = $event->element;

            // Attach behavior to element
            $element->attachBehavior('elementStatusEvents', ElementStatusBehavior::class);

            // Call onBeforeSaveStatus if not a new element
            if (!$event->isNew) {
                $element->onBeforeSaveStatus();
            }
        });

        // After saving an element
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function(ElementEvent $event) {
            /** @var Element $element */
            $element = $event->element;

            // Call onAfterSaveStatus if element has the behavior
            if ($element->getBehavior('elementStatusEvents') !== null) {
                $element->onAfterSaveStatus();

                if ($element->statusChanged) {
                    $this->_elementsChanged[] = $element;
                }
            }
        });

        // After preparing response
        Craft::$app->getResponse()->on(Response::EVENT_AFTER_PREPARE, function() {
            if (!empty($this->_elementsChanged)) {
                // Trigger a 'statusesChanged' event
                if ($this->hasEventHandlers(self::EVENT_ELEMENT_STATUSES_CHANGED)) {
                    $this->trigger(self::EVENT_ELEMENT_STATUSES_CHANGED, new ElementStatusesEvent([
                        'elements' => $this->_elementsChanged,
                    ]));
                }
            }
        });
    }
}