<?php
// E2E Fixture: Legacy PHP 5.6 style service class

class UserService
{
    public $db;

    public function findUser(string $id = null)
    {
        if (0 == $id) {
            return null;
        }

        $result = $this->db->query("SELECT * FROM users WHERE id = $id");
        if (is_resource($result)) {
            return $result;
        }

        return null;
    }

    public function processUsers(array $users = null)
    {
        $filtered = [];
        foreach ($users ?? [] as $user) {
            if ("" == $user['name']) {
                continue;
            }
            $filtered[] = $user;
        }
        return $filtered;
    }

    public function configure()
    {
        $this->timeout = 30;
        $this->retries = 3;
    }
}
