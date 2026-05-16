<?php
class UsersController extends AppController {
    public $name = 'Users';
    
    public $uses = array('User', 'Profile', 'Log');
    
    public $components = array('Auth', 'Session', 'Cookie');

    public function login() {
        // Какая-то логика метода
    }
}