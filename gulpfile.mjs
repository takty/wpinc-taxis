/**
 * Gulp file
 *
 * @author Takuto Yanagida
 * @version 2022-12-08
 */

const SRC_JS_RAW  = ['src/**/*.js', '!src/**/*.min.js'];
const SRC_JS_MIN  = ['src/**/*.min.js'];
const SRC_PHP     = ['src/**/*.php'];
const SRC_PO      = ['src/languages/**/*.po'];
const SRC_JSON    = ['src/languages/**/*.json'];
const DEST        = './dist';

import gulp from 'gulp';

import { makeJsTask } from './gulp/task-js.mjs';
import { makeCopyTask } from './gulp/task-copy.mjs';
import { makeLocaleTask }  from './gulp/task-locale.mjs';

const js_raw  = makeJsTask(SRC_JS_RAW, DEST, 'src');
const js_min  = makeCopyTask(SRC_JS_MIN, DEST);
const js      = gulp.parallel(js_raw, js_min);
const php     = makeCopyTask(SRC_PHP, DEST);
const po      = makeLocaleTask(SRC_PO, DEST, 'src');
const json    = makeCopyTask(SRC_JSON, DEST, 'src');

const watch = done => {
	gulp.watch(SRC_JS_RAW, gulp.series(js_raw));
	gulp.watch(SRC_JS_MIN, gulp.series(js_min));
	gulp.watch(SRC_PHP, gulp.series(php));
	gulp.watch(SRC_PO, po);
	gulp.watch(SRC_JSON, json);
	done();
};

export const build = gulp.parallel(js, php, po, json);
export default gulp.series(build , watch);
