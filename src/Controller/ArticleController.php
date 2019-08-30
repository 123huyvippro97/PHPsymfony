<?php 
	namespace App\Controller;
	use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
	use Symfony\Component\HttpFoundation\Response;
	class ArticleController{

		/**	
		* @Route("/");
		**/
		public function homepage(){
			return new Response('My First page');
		}
		/**	
		* @Route("/news/{slug}");
		**/
		//need route match news/anything ~~ use slug

		public function show($slug)
		{
			return new Response(sprintf(
				'Future page to show the article: %s',$slug
		));
		}
	}
