(function($){

	var myPostsMetaBoxView = Backbone.View.extend({
		el: ".ef-myposts-content-type",

		initialize: function(args) {
			this.$refreshButton = $('a.ef-myposts-refresh');
			
			this.listenTo(this.collection, 'reset', this.resetRefreshButton);
			this.listenTo(this.collection, 'reset', this.changeActiveType);
		},

		events: {
			'click a.ef-myposts-button': 'fetchContentTypeSelected',
			'click a.ef-myposts-refresh': 'refreshActiveContentType'
		},

		changeActiveType: function() {
			this.$el.find('a.ef-myposts-button.active').removeClass('active');
			this.$el.find('a[data-type="' + this.collection.options.activeContentType + '"]').addClass('active');
		},

		resetRefreshButton: function() {
			if( this.$refreshButton.hasClass('loading') )
				this.$refreshButton.html('Refresh').removeClass('loading');
		},

		refreshActiveContentType: function() {
			this.$refreshButton.html( '<span class="spinner" style="display:block">&nbsp;</span>' ).addClass('loading');
			this.collection.getItemFollowing(this.collection.options.activeContentType);
			return false;
		},

		fetchContentTypeSelected: function(event) {
			this.collection.options.activeContentType = $(event.target).attr('data-type');
			this.collection.getItemFollowing(this.collection.options.activeContentType);
			return false;
		}
	});

	var contentItemsView = Backbone.View.extend({
		el: '.ef-myposts-content-items',
		template: Handlebars.compile($('#ef-myposts-posts-templ').html()),

		initialize: function() {
			this.listenTo(this.collection, 'reset', this.setTemplate);
			this.listenTo(this.collection, 'reset', this.render);
		},

		setTemplate: function() {
			this.template = Handlebars.compile($('#ef-myposts-' + this.collection.options.activeContentType + '-templ').html())
		},

		render: function() {
			this.$el.html(this.template({'content_items': this.collection.toJSON()}));
		}
	});

	var contentItemsCollection = Backbone.Collection.extend({
		initialize: function() {
			this.options = {
				'activeContentType' : 'posts'
			};
		},
		//Dat business logic.
		getItemFollowing: function(item_type){
			var options = {
				action: 'get-items-following',
				itemType: item_type ? item_type : 'posts',
				itemNonce: $('#ef-myposts-get-posts-following').val(),
			};
			
			(function(t){
				$.post( ajaxurl, options )
					.done(function(msg){
						t.reset(msg.data);
					})
					.fail( function(msg){
						console.log(msg);
					});
			})(this)
		}
	});

	var contentItems = new contentItemsCollection();
	var mypostsView = new myPostsMetaBoxView({ 'collection': contentItems });
	var mypostsItemsView = new contentItemsView({'collection': contentItems});

	//Handlebar helpers
	Handlebars.registerHelper('post_message', function(context, options) {
		var status_msg;

		if( context.status == 'publish' ) {
			status_msg = 'Published';
		} else {
			status_msg = 'Updated';
		}
		
		return new Handlebars.SafeString( status_msg + ' ' + context.human_time + ' ago by ' + context.user );
	});

})(jQuery);