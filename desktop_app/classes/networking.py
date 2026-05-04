
from classes.common_lib import *


def is_another_instance_running(port):
    #
    #Verifica se un'altra istanza dell'applicazione e' gia' attiva su una determinata porta.
    #
    server = QTcpServer()
    if not server.listen(QHostAddress(QHostAddress.SpecialAddress.LocalHost), int(port)):
        return True
    global _keeper
    _keeper = server
    return False
