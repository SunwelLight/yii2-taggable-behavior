Taggable behavior for Yii 2
===========================

This extension allows you to get functional for tagging.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```sh
php composer.phar require 2amigos/yii2-taggable-behavior "*"
```

or add

```json
"2amigos/yii2-taggable-behavior": "*"
```

to the require section of your `composer.json` file.

Configuring
--------------------------

First you need to configure model as follows:

```php
class Post extends ActiveRecord
{
	public function behaviors() {
		return [
			[
				'class' => Taggable::className(),
			],
		];
	}
}
```

Second you need to configure query model as follows:

```php
class PostQuery extends ActiveQuery
{
	public function behaviors() {
		return [
			[
				'class' => TaggableQuery::className(),
			],
		];
	}
}
```

> [![2amigOS!](http://www.gravatar.com/avatar/55363394d72945ff7ed312556ec041e0.png)](http://www.2amigos.us)  
<i>Web development has never been so fun!</i>  
[www.2amigos.us](http://www.2amigos.us)
