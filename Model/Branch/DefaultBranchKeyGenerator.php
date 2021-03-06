<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */
namespace Diamante\DeskBundle\Model\Branch;

class DefaultBranchKeyGenerator implements BranchKeyGenerator
{
    /**
     * Generate Branch key
     * @param string $name
     * @return string
     * @throws \InvalidArgumentException if given $name is invalid
     */
    public function generate($name)
    {
        if (false === $this->validate($name)) {
            throw new \InvalidArgumentException('Can not generate key from given name. Name should have at list 2 letters.');
        }

        $name = preg_replace('/[^a-zA-Z\s]/', '', $name);
        $name = trim($name);
        $parts = explode(' ', $name);
        if (count($parts) == 1) {
            $length = strlen($name) < 4 ? strlen($name) : 4;
            return strtoupper(substr($name, 0, $length));
        }

        $key = '';
        foreach ($parts as $part) {
            $key .= strtoupper(substr($part, 0, 1));
        }
        return $key;
    }

    /**
     * Check if given name is valid
     * @param string $name
     * @return bool
     */
    private function validate($name)
    {
        $name = preg_replace('/[^a-zA-Z]/', '', $name);
        if (strlen($name) < 2) {
            return false;
        }
        return true;
    }
} 
