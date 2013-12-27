Similar Products for Magento
=============

Magento Upsell Product Enhancement with PredictionIO

## Install

Clone the git repo - `git clone git://github.com/richdynamix/Similar-Products.git` - or https://github.com/richdynamix/Similar-Products/archive/master.zip

Once downloaded copy the contents (app) folders to your project root merging with your existing magento installation.

### Requirements

Must have an instance of PredictionIO server setup and ready to accept data. Please see the docs for information.

### Features

* Replace upsell products with products defined by PredictionIO
* Reverts to admin defined upsells when there is no data returned by PredictionIO
* Product View Actions
* Product Sale Actions
* Guest Action Logging

#### Replacing Upsell Products

Configure the module to make API calls to your instance of PredictionIO, defining the host, port and engine (name and key) and data will be recorded when the user is logged in.

#### Revert to Default Upsells

When the module is disabled or the user is not logged in or PredictionIO has not returned any matching products then the module will silently revert to magento's built in upsell products allowing store admin to set these manually.

#### Product View Actions

When a customer views a product page the module will make an API call to add the product to the PredictionIO server as well as record the action of view

#### Product Sale Actions

When a customer places as an order then the module will get the parent product of the purchased simple product if available and post its ID to PredictionIO as only parent products can show the upsells.

#### Guest Action Logging

Sometimes customers don't login till they get to the checkout so we log the customers actions in the session to post to PredictionIO when the customer logs in.

#### PredictionIO

* Web: [http://prediction.io]
* Docs: [http://docs.prediction.io/current/]
* Twitter: https://twitter.com/predictionio

#### Authors

* Magento Module was developed by Steven Richardson - https://twitter.com/troongizmo

  [http://prediction.io]: http://prediction.io
  [http://docs.prediction.io/current/]: http://docs.prediction.io/current/