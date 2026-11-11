=== Hukamnama · Sri Harmandir Sahib ===
Contributors: algorythmos
Tags: hukamnama, gurbani, sikh, gurdwara, golden temple
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

The daily Hukamnama from Sri Harmandir Sahib, Amritsar, on your site. Every line is checked before it is shown.

== Description ==

Add the shortcode `[hukamnama]` to any page, post or widget area. It shows the Hukamnama from Sri Harmandir Sahib (the Golden Temple), Amritsar: the whole shabad in Gurmukhi, its date in Amritsar, the citation "Sri Guru Granth Sahib Ji · Ang N" and a credit link.

What it will not do:

* It never shows a line that fails its check. Each day arrives with a hash of its lines; the plugin computes the hash again and shows the text only when the two match.
* It never shortens the shabad, and never changes a character of the Gurmukhi.
* It never calls a Hukamnama "today's" unless its date is the date in Amritsar. Before the morning's Hukamnama is published, the most recent one is shown under its own date.

Options:

* `[hukamnama translit="yes"]` adds a transliteration below, under its own label.
* `[hukamnama mode="embed"]` shows gurbanisoul.com's own small embed in a frame instead. With `lang="en"`, `lang="pa"` or `lang="fr"`, and `theme="auto"`, `"light"` or `"dark"`.

The Hukamnama is published by SGPC. This plugin and the service it reads are not affiliated with SGPC: the service finds each day's shabad by Ang and serves its text from Sri Guru Granth Sahib Ji.

== External services ==

This plugin reads the Hukamnama from the Hukamnama API of Gurbani Soul, at `https://api.gurbanisoul.com`. It is needed to know which shabad is the day's Hukamnama and to receive its text.

* When: at most once every five minutes, when a page with the shortcode is viewed.
* What is sent: an ordinary web request from your server (its IP address and a user-agent naming this plugin). Nothing about your visitors is sent.
* With `mode="embed"`, the visitor's browser loads a page from `https://gurbanisoul.com/embed/hukamnama` in a frame. That page runs no script and sets no cookie.

The service is provided by Algorythmos Pty Ltd: [terms](https://gurbanisoul.com/en/developers#terms), [privacy](https://gurbanisoul.com/en/privacy).

== Installation ==

1. Upload the `hukamnama-sri-harmandir-sahib` folder to `/wp-content/plugins/`.
2. Activate the plugin on the Plugins screen.
3. Add `[hukamnama]` where the Hukamnama should appear.

== Frequently Asked Questions ==

= The page says the Hukamnama cannot be shown right now. =

The plugin could not reach the service, or a day failed its check, and it has no checked Hukamnama from the last two days to show instead. It tries again after a minute. Late is better than wrong.

= Can I style it? =

Yes. The text is inside `.hukamnama-shs`; the Gurmukhi lines are in `.hukamnama-shs-lines`. Please do not hide the citation or the credit.

== Changelog ==

= 1.0.0 =
* First version.
