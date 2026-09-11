#!/usr/bin/env node
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const source = fs.readFileSync( path.join( __dirname, '..', 'assets', 'admin.js' ), 'utf8' );

assert.match( source, /mode: \{ name: 'php', startOpen: true \},\s*lint: false/ );
assert.match( source, /editor\.codemirror\.setOption\('lint', false\);/ );
assert.match( source, /editor\.settings\.codemirror\.mode = mode;/ );
assert.match( source, /editor\.settings\.codemirror\.lint = !isPhp;/ );
assert.match( source, /editor\.codemirror\.setOption\('lint', !isPhp\);/ );

console.log( 'Snippet editor keeps tagless PHP in PHP mode and toggles lint by code type.' );
