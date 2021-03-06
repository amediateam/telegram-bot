<?php

namespace Telegram\Bot\Objects;

/**
 * Class GameHighScore.
 *
 * @method int   getPosition() Position in high score table for the game.
 * @method User  getUser()     User.
 * @method int   getScore()    Score.
 */
class GameHighScore extends BaseObject
{
    /**
     * Property relations.
     *
     * @return array
     */
    public function relations()
    {
        return [
            'user' => User::class,
        ];
    }
}
