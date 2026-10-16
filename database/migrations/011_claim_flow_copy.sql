-- The result page now has a "Preuzmi nagradu" claim form, so the old
-- "save the PDF and contact the team" instruction is obsolete.
UPDATE prizes SET instructions = '' WHERE instructions = 'Sačuvajte PDF potvrdu i javite se PlayWorld timu za preuzimanje nagrade.';
UPDATE draws SET snapshot_instructions = '' WHERE snapshot_instructions = 'Sačuvajte PDF potvrdu i javite se PlayWorld timu za preuzimanje nagrade.';
