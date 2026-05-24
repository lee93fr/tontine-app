<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'locale', 'subject', 'body'];

    public static function getFor(string $key, string $locale): ?static
    {
        return static::where('key', $key)->where('locale', $locale)->first();
    }

    public function replaceVars(array $vars): static
    {
        $clone = clone $this;
        foreach ($vars as $k => $v) {
            $clone->subject = str_replace("{{$k}}", $v, $clone->subject);
            $clone->body    = str_replace("{{$k}}", $v, $clone->body);
        }
        return $clone;
    }

    public function buildMailMessage(string $greeting, ?string $actionLabel = null, ?string $actionUrl = null): MailMessage
    {
        $msg = (new MailMessage)->subject($this->subject)->greeting($greeting);

        foreach (explode("\n\n", $this->body) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $msg->line($line);
            }
        }

        if ($actionLabel && $actionUrl) {
            $msg->action($actionLabel, $actionUrl);
        }

        return $msg;
    }
}
