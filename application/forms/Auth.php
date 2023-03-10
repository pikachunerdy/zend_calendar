<?php
class Application_Form_Auth extends Zend_Form
{
    public function init()
    {
        $username = $this->addElement('text', 'username', array('filters' => array('StringTrim', 'StringToLower'),
            'validators' => array('Alnum',
            array('StringLength', false, array(2, 20)),),
            'required' => true,
            'label' => 'Username:',));

        $password = $this->addElement('password', 'password', array('filters' => array('StringTrim'),
            'validators' => array('Alnum',
            array('StringLength', false, array(2, 20)),),
            'required' => true,
            'label' => 'Password:',));

        $login = $this->addElement('submit', 'login', array('required' => false,
            'ignore' => true,
            'label' => 'Submit',));
        // We want to display a 'failed authentication' message if necessary;
        // we'll do that with the form 'description', so we need to add that
        // decorator.
        $this->setDecorators(array('FormElements',
            array('HtmlTag', array('tag' => 'dl', 'class' => 'zend_form')),
            array('Description', array('placement' => 'prepend')),
            'Form'));

        $this->addDisplayGroup(array('username', 'password', 'login'), 'login_fields', array('legend' => 'Login here'));
        return $this;
    }
}
?>
