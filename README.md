# Alma/Flywire integration

This application provides integration between ExLibris Alma and Flywire, to invoice Patron fines and fees for online payment.

## Expected workflow

* Patron initiates an AJAX call from Primo, indicating they want to pay fees online
* Application responds to authenticated AJAX request by
  * Querying Alma for existing fines and fees
  * Deleting any unpaid fine/fee invoices from Flywire
  * Creating a new invoice of fines/fees in Flywire
  * Responding with a JSON indicating success/failure/nothing-to-do.

## Author / License

Written by Clinton Graham for the [University of Pittsburgh](http://www.pitt.edu).  Copyright (c) University of Pittsburgh.

Released under a license of GPL v2 or later.
