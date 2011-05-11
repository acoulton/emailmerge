# About EmailMerge

The [EmailMerge] library provides a full implementation of creating user-customisable
emails based on a template and some data. Think MS Word MailMerge meets Kohana -
complete with the option to customise individual emails prior to sending.

## Features

 * Provides a complete user interface for building and sending merges
 * Provide pre-built templates with your app, and allow users to modify and create their own
 * Namespaced templates - separate templates for different application contexts
 * Automatic parameter hinting
 * Templates support markdown for formatted emails

## What it is and is not for!

EmailMerge is designed to be used when you need to allow a user to preview or customise
a mailmerge to a relatively small number of recipients, and particularly if the user
might want to

[!!] **Don't** use EmailMerge for your e-bulletin, or generally for merging large
volumes of data in a predictable way with a relatively infrequently changing template.
SwiftMailer and various other modules have much higher performance implementations of this.

## Dependencies

 * [Banks' kohana-email SwiftMailer port](https://github.com/banks/kohana-email.git)
 * [Shadowhand's UUID module](https:://github.com/shadowhand/uuid.git)
 * Kohana 3.0
 * A markdown parser - if markdown cannot be found within vendor/markdown in the
   CFS then the class will try to include the markdown parser from
   MODPATH/userguide/vendor/markdown so on a standard Kohana install it will function
   even if userguide is disabled.

## Development roadmap

Coming soon:

 * Update to work against @shadowhand's more up to date Swiftmailer module
 * Develop KO3.1 branch
 * Email file attachments
 * Custom markdown parser to generate more sensible text-only email bodies for fallback clients
 * WYSIWYG editor and signature support?
 * Progress bar during sending, and better handling of failed mails (retry / log / etc)

## Got ideas?

Feature and bug requests are very welcome on Github - although forks and pulls are
much preferred!