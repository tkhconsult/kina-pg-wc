<?php

namespace TkhConsult\KinaBankGateway\KinaBank;

/**
 * Class Form
 *
 * @package TkhConsult\KinaBankGateway\KinaBank
 */
class Form
{
    const ELEMENT_TEXT   = 'text';
    const ELEMENT_HIDDEN = 'hidden';
    const ELEMENT_SUBMIT = 'submit';
    const ELEMENT_BUTTON = 'button';

    /**
     * @var string
     */
    private $_formName = '';

    /**
     * @var string
     */
    private $_formAction = '';

    /**
     * @var string
     */
    private $_formMethod = 'POST';

    /**
     * @var array
     */
    private $_formElements = [];
    
    private $showAccept = false;
    private $acceptUrl = '';
    private $submitButtonLabel = 'Checkout - Credit/Debit Cards';
    private $pageType = 'embedded';

    /**
     * Construct
     *
     * @param string $formName
     */
    public function __construct($formName = '')
    {
        if (empty($formName)) {
            $formName = 'form-'.rand();
        }
        $this->_formName = $formName;
        $this->init();

        return $this;
    }

    /**
     * @return $this
     */
    public function init()
    {
        return $this;
    }

    /**
     * Add a text element
     *
     * @param        $elementName
     * @param string $elementValue
     * @param array  $elementOptions
     *
     * @return $this
     */
    public function addTextElement($elementName, $elementValue = '', $elementOptions = [])
    {
        $this->_formElements[$elementName] = $this->_renderElement(self::ELEMENT_TEXT, $elementName, $elementValue, $elementOptions);

        return $this;
    }

    /**
     * @param $type
     * @param $elementName
     * @param $elementValue
     * @param $elementOptions
     *
     * @return string
     */
    protected function _renderElement($type, $elementName, $elementValue, $elementOptions)
    {
        $options = '';
        if (is_array($elementOptions)) {
            foreach ($elementOptions as $name => $value) {
                $options .= ' '.$name.'="'.$value.'"';
            }
        }
        $label = '';
        if ($type != self::ELEMENT_HIDDEN) {
            $label = '&nbsp;<label>'.$elementName.'</label>';
        }

        return '<div><input type="'.$type.'" name="'.$elementName.'" data-kinabank value="'.$elementValue.'"'.$options.'/>'.$label . '</div>';
    }

    /**
     * Add a hidden element
     *
     * @param        $elementName
     * @param string $elementValue
     * @param        $elementOptions
     *
     * @return $this
     */
    public function addHiddenElement($elementName, $elementValue = '', $elementOptions = [])
    {
        $this->_formElements[$elementName] = $this->_renderElement(self::ELEMENT_HIDDEN, $elementName, $elementValue, $elementOptions);

        return $this;
    }

    /**
     * @param $action
     *
     * @return $this
     */
    public function setFormAction($action)
    {
        $this->_formAction = $action;

        return $this;
    }

    /**
     * @param $method
     *
     * @return $this
     */
    public function setFormMethod($method)
    {
        $this->_formMethod = $method;

        return $this;
    }


    /**
     * Accept URL setter
     *
     * @param boolean $debug
     *
     * @return $this
     */
    public function setAcceptUrl($acceptUrl)
    {
        $this->acceptUrl = $acceptUrl;
        $this->showAccept = !empty($acceptUrl);

        return $this;
    }

    /**
     * Submit button label setter
     *
     * @param boolean $debug
     *
     * @return $this
     */
    public function setSubmitButtonLabel($label)
    {
        $this->submitButtonLabel = $label;

        return $this;
    }

    /**
     * Page type setter
     *
     * @param boolean $pageType
     *
     * @return $this
     */
    public function setPageType($pageType)
    {
        $this->pageType = $pageType;

        return $this;
    }
    
    /**
     * Renders form HTML
     *
     * @param bool $autoSubmit
     *
     * @return string
     */
    public function renderForm($autoSubmit = true)
    {
        ob_start();
        $formId = 'kblPaymentForm';
        $formName = $this->_formName;
        $formMethod = $this->_formMethod;
        $formAction = $this->_formAction;
        $submitLabel = $this->submitButtonLabel;
        $showAccept = $this->showAccept;
        $acceptUrl = $this->acceptUrl;
        $isHosted = $this->pageType == 'hosted';
        $scheme = parse_url($formAction, PHP_URL_SCHEME);
        $host = $scheme . '://' . parse_url($formAction, PHP_URL_HOST);
        $elements = implode("\n", $this->_formElements);
        include "payment-form.php";
        return  ob_get_clean();
    }
}