## Unreleased

* [BREAKING] Remove transparent controller and shadowhand/email - implement
  your own Controller_Emailmerge and implement a method to return a swift
  mailer instance.
* [BREAKING] No out-of-the box rate limiting / throttling - implement your
  own.
* [BREAKING] Templates are now always JSON and in a fixed directory on disk,
  not the cascading filesystem.

