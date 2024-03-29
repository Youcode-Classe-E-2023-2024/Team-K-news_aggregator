<?php

namespace App\Http\Controllers;

use App\Mail\NewNotification;
use App\Models\Categories;
use App\Models\Post;
use App\Models\Preference;
use App\Models\SourceRss;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RssManage extends Controller
{

    /***
     * show all categories in the form
     * show all rss links
     */
    public function index()
    {
        $categories = Categories::all();

        $links = SourceRss::all();

        return view('dashboard', compact('categories', 'links'));
    }

    /**
     * inserts a new rss to source table
     */
    public function newRss(Request $r)
    {
        $source = new SourceRss();
        $source->category_id = $r->category_id;
        $source->rss_link = $r->link;
        $source->name = $r->name;
        $source->save();

        $this->insertPost($r->link, $r->category_id);
        $this->sendEmailNotificationToInterestedUsers($r->category_id);

        return back();
    }


    public function insertPost($link, $category_id)
    {
        $rss_feed_data = file_get_contents($link);
        $rss = simplexml_load_string($rss_feed_data);

        foreach ($rss->channel->item as $item) {
            $p = new Post();
            $p->title = $item->title;
            $p->slug = Str::slug($item->title);
            $p->description = $item->description;
            $p->category_id = $category_id; // Assign the provided category ID
            $p->image = isset($item->enclosure['url']) ? (string)$item->enclosure['url'] : null; // Check if image URL is available
            $p->save();
        }
        // Clear cache related to posts or categories after insertion
        Cache::forget('categories');
            Cache::forget('posts_' . $category_id);
        return redirect('/Rss');
    }

    /***
     * envoyer un email apres l'insertion des postes
     */
    private function sendEmailNotificationToInterestedUsers($categories)
    {
        $preferences = Preference::where('category_id', $categories)->get();

        foreach ($preferences as $preference) {
            $user = User::find($preference->user_id); // Assuming there's a relationship between Preference and User
            if ($user) {
                Mail::to($user->email)->send(new NewNotification($user));
            }
        }
    }

    /**
     * delete Rss link
     */
    public function destroyLink($id)
    {
        $link = SourceRss::findOrFail($id);
        $link->delete();
        return redirect('/Rss');
    }
}
