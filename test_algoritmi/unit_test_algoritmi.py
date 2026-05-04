import unittest
import json
from test_algoritmi import find_duplicate_emails, group_user_events, get_max_depth, load_json_static

class UnitTestAlgoritmiStatic(unittest.TestCase):

    def test_b1_static(self):
        """Verifica B1 caricando staticamente data_b1.json."""
        data = load_json_static("B1")
        if data is None: self.fail("File data_b1.json mancante")
        result = find_duplicate_emails(data)
        self.assertTrue(len(result) >= 1)

    def test_b2_static(self):
        """Verifica B2 caricando staticamente data_b2.json."""
        data = load_json_static("B2")
        if data is None: self.fail("File data_b2.json mancante")
        result = group_user_events(data)
        
        # CAMBIA "10" (stringa) in 10 (intero)
        self.assertIn(10, result)

    def test_b3_static(self):
        """Verifica B3 caricando staticamente data_b3.json."""
        data = load_json_static("B3")
        if data is None: self.fail("File data_b3.json mancante")
        self.assertGreaterEqual(get_max_depth(data), 1)

if __name__ == "__main__":
    unittest.main()