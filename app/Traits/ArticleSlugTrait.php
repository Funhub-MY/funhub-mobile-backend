<?php
namespace App\Traits;

use App\Models\Article;
use Illuminate\Support\Str;

/**
 * A class handles article slug.
 */
trait ArticleSlugTrait {
    /**
     * @throws \Exception
     */
    public function formatSlug($text): string
    {

        if ($text == '' || $text == null) {
            throw new \Exception('Text must not be empty or null.');
        }
        // try Str::slug first, then see if it is empty or null
        $slug_text = Str::slug($text);

        if ($slug_text == '' || $slug_text == null) {
            // if it is empty or null, usually it is caused by chinese characters
            // if empty, randomly assigned 6 characters and numbers as slug.

            return $this->generateSlugRandomTextAndNumber();
        }
        // if not null, lets fetch to see if this slug has being used in article slug
        // as article slug is unique, there must not be any duplication.
        $article = Article::where('slug', $slug_text)->first();
        if ($article) {
            // if fetch article slug exists, do the same slug, but add another 6 random characters and 6 random numbers behind.
            // for e.g. abc-skfhgq-123456
            return $this->generateSlugRandomTextAndNumber($slug_text);
        }
        // if not exists, then just str:slug it and return it back.
        return $slug_text;
    }

    /**
     * @throws \Exception
     */
    public function generateSlugRandomTextAndNumber($concat = ''): string
    {
        // generate random 6 text and number and format to E.G: abcdef-123456
        $random_text = Str::lower(Str::random('6'));
        $random_number = random_int(100000, 999999);
        if ($concat == '' || $concat == null) {
            return $random_text.'-'.$random_number;
        } else {
            return $concat.'-'.$random_text.'-'.$random_number;
        }
    }
}
