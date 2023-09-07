DELETE FROM ?:payment_descriptions WHERE payment_id IN (SELECT payment_id FROM ?:payments WHERE processor_id IN (SELECT processor_id FROM ?:payment_processors WHERE processor_script = 'paymentsenserp.php'));
DELETE FROM ?:payments WHERE processor_id IN (SELECT processor_id FROM ?:payment_processors WHERE processor_script = 'paymentsenserp.php');
DELETE FROM ?:payment_processors WHERE processor_script = 'paymentsenserp.php';

