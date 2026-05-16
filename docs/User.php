<?php
class User extends AppModel {
    public $name = 'User';

    public $belongsTo = array(
        'Group' => array(
            'className' => 'Group',
            'foreignKey' => 'group_id'
        )
    );

    public $hasMany = array('Post', 'Comment');
}