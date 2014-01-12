module.exports = function(grunt) {
  var env = grunt.option('env') || 'default';

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    watch: {
      all: {
        files: [ 
                'common/js/ef_date.js',
                'common/js/screen-options.js',
                'modules/**/*.js'
              ],
        tasks: ['jshint'],
        options: {
          interrupt: true,
          spawn: false
        }
      }
    },
    jshint: {
      options: grunt.file.readJSON('.jshintrc'),
      all:  {
        src: [ 
          'common/js/ef_date.js',
          'common/js/screen-options.js',
          'modules/**/*.js'
        ]
      }
    },
    uglify: {
      options: {
        warnings: true
      },
      build: {
        files: [
            {
              expand: true,
              src: 'common/js/ef_date.js', 
              dest: 'common/js/ef_date.min.js', 
            },
            {
              expand: true,
              src: 'common/js/screen-options.js', 
              dest: 'common/js/screen-options.min.js', 
            },
            {
              expand: true,
              src: 'modules/**/*.js',
              dest: 'modules/**/*.min.js'
            }
        ]
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-jshint');

  //Only watch on files that are saved.
  grunt.event.on('watch', function(action, filepath) {
    grunt.config('jshint.all.src', filepath);
  });

  if( env == 'prod' )
    grunt.registerTask( 'default', [ 'jshint', 'uglify'] );
  else
    grunt.registerTask( 'default', ['watch'] );

};
