<?php
class ArticlesController extends AppController{
	public $uses = array('Article', 'Like', 'Comment'); // Controlle内で他のModel(table)を利用できるようにする
	public $helpers = array('Html', 'Form', 'Session'); // viewの拡張機能を呼び出す
	public $components = array('Session', 'Paginator'); // Controllerの拡張機能を呼び出す
	public $paginate_new = array( // Paginatorの設定
		'limit' => 5,
		'order' => array(
			'Article.created' => 'desc' // 新着順
		)
	);
	public $paginate_likes = array(
		'limit' => 5,
		'order' => array(
			'Article.likes' => 'desc' // 人気順
		)
	);

	public function index(){
	
		///////////  ソート内容によってページネーションの設定を変更する　///////////
		if( ($this->request->query('sort') == 1) || !($this->request->query('sort')) ){
			$this->Paginator->settings = $this->paginate_new;
		}

		if($this->request->query('sort') == 2){
			$this->Paginator->settings = $this->paginate_likes;
		}

		///////////			カテゴリ指定・検索内容の有無			///////////
		if($this->request->query('category_id')){ // getの値を取得するコマンド
			$category_id = $this->request->query('category_id');
			$articles = $this->Paginator->paginate('Article', array('Article.del_flg' => '0', 'Article.category_id' => $category_id)); // アソシエーションによりdel_flgが２つ存在するので「モデル名.del_flg」で指定
		}

		if(!($this->request->query('category_id'))){ // getで値が取得できない場合(ALL)
			$category_id = 0;
			$articles = $this->Paginator->paginate('Article', array('Article.del_flg' => '0'));
		}

		if($this->request->query('search_word')){
			$search_word = $this->request->query('search_word');
			$articles = $this->Paginator->paginate('Article', array('Article.del_flg' => '0', 'OR' => array ('Article.title LIKE' => '%'.$search_word.'%', 'Article.detail LIKE' => '%'.$search_word.'%', 'User.nickname LIKE' => '%'.$search_word.'%')));

			// return $this->redirect(array('action' => 'detail', $id));
		}

		///////////  Viewにデータを渡す　///////////
		$this->set('articles', $articles);
		$this->set('category_id', $category_id);
	}

	public function detail($id = null){ // このidはarticleのid
		if(!$id){
			throw new NotFoundException(__('このページは存在しません'));
		}

		$article = $this->Article->findById($id);
		if(!$article){
			throw new NotFoundException(__('データがありません'));
		}
		$this->set('article', $article);

		// 該当記事に関するコメントをviewに渡す
		$comments = $this->Comment->getComments($id);
		$this->set('comments', $comments);

		// コメント処理を行う
		if($this->request->is('post')){
			$this->Comment->create();
			if($this->Comment->save($this->request->data)){
				$this->Session->setFlash(__('コメントが反映されました'));
				return $this->redirect($this->referer()); // いまいるページにリダイレクトする
			}
			$this->Session->setFlash(__('コメントできませんでした'));
		}

		// 該当ユーザーの該当記事へのいいねの有無を調べてviewに渡す
		$like = $this->Like->findAllByUserIdAndArticleId(1, $id); // 実際にはログインユーザーに変更する
		$this->set('like', $like);
	}

	public function deleteComment($id){
		if($this->request->is('get')){
			throw new MethodNotAllowedException(__('このページは無効です'));
		}

		$data = array('Comment' => array('id' => $id, 'del_flg' => 1)); // 更新する内容を設定
		$fields = array('del_flg'); // 更新する項目(フィールド指定)
		if($this->Comment->save($data, false, $fields)){
			$this->Session->setFlash(__('このコメント(id=%s)は削除されました', h($id)));
			return $this->redirect($this->referer());
		}
	}

	public function create(){
		if($this->request->is('post')){
			if($this->Article->save($this->request->data)){
				$this->Session->setFlash(__('あなたの雑学が投稿されました'));
				return $this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash(__('雑学の投稿に失敗しました'));
		}
	}

	public function edit($id = null){
		if(!$id){
			throw new NotFoundException(__('このページは存在しません'));
		}

		$article = $this->Article->findById($id);
		if(!$article){
			throw new NotFoundException(__('データがありません'));
		}

		// editページにアクセスした際にフォームにデータをセットしておく
		if(!$this->request->data){
			$this->request->data = $article;
		}

		//編集ボタンが押された場合に、DBへの保存処理を行う
		if($this->request->is(array('post', 'put'))){
			$this->Article->id = $id;
			if($this->Article->save($this->request->data)){
				$this->Session->setFlash(__('雑学が編集されました'));
				return $this->redirect(array('action' => 'detail', $id));
			}
			$this->Session->setFlash(__('雑学の編集に失敗しました'));
		}
	}

	public function delete($id = null){
		if($this->request->is('get')){
			throw new MethodNotAllowedException(__('このページは無効です'));
		}

		$data = array('Article' => array('id' => $id, 'del_flg' => '1')); // 更新する内容を設定
		$fields = array('del_flg'); // 更新する項目(フィールド指定)
		if($this->Article->save($data, false, $fields)){
			$this->Session->setFlash(__('この雑学(id=%s)は削除されました', h($id)));
			return $this->redirect(array('action' => 'index'));
		}
	}

	public function like($article_id = null, $user_id = null){ // このidはarticleのid
		if($this->request->is('get')){
			throw new MethodNotAllowedException();
		}

		$article = $this->Article->findById($article_id);

		// likeテーブルに追加する
		$data = array('Like' => array('article_id' => $article_id, 'user_id' => $user_id));
		$fields = array('article_id', 'user_id'); // 登録する項目(フィールド指定)
		$this->Like->save($data, false, $fields); // 登録

		// articleテーブルを変更する
		$data2= array('Article' => array('id' => $article_id, 'likes' => $article['Article']['likes']+1)); // 更新する内容を設定
		$fields2 = array('likes'); // 更新する項目(フィールド指定)
		$this->Article->save($data2, false, $fields2);

		$this->Session->setFlash(__('この記事をイイネしました!'));
		return $this->redirect($this->referer());
	}

	public function resetLike($article_id = null, $user_id =null){
		if($this->request->is('get')){
			throw new MethodNotAllowedException();
		}

		// likeテーブルからdataを削除する
		$like = $this->Like->findByArticleIdAndUserId($article_id, $user_id);
		$like_id = $like['Like']['id'];
		$this->Like->delete($like_id);

		// articleテーブルを変更する
		$article = $this->Article->findById($article_id);
		$data = array('Article' => array('id' => $article_id, 'likes' => $article['Article']['likes']-1)); // 更新内容
		$fields = array('likes'); // 更新する項目
		$this->Article->save($data, false, $fields);

		$this->Session->setFlash(__('この記事へのイイネを取り消しました'));
		return $this->redirect($this->referer());
	}

}