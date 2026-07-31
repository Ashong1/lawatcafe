<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemAlert extends Notification
{
    use Queueable;

    protected $title;

    protected $message;

    protected $icon;

    protected $url;

    public function __construct($title, $message, $icon = 'bell', $url = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->icon = $icon;
        $this->url = $url;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'url' => $this->url,
        ];
    }
}
