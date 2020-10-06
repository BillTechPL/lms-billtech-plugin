#!/usr/bin/python
#-*- coding: utf-8 -*-

def getCashLinkByDocument(cursor, document_id):
    print document_id
    cursor.execute(
    "select bpl.link from billtech_payment_links bpl left join cash c on bpl.src_cash_id = c.id \
     where c.docid = %s;" % document_id)

    return cursor.fetchone()[0] if cursor.fetchone() else None

def getBalanceLinkByCustomer(cursor, customer_id):
    cursor.execute(
    "select bpl.link from billtech_payment_links bpl left join cash c on bpl.src_cash_id = c.id \
    where customer_id = %s order by c.time desc limit 1" % customer_id)

    query_result = cursor.fetchone()
    if query_result:
       query_result = list(query_result)
       return query_result[0] + "&type=balance"
    else:
        return None

def createSingleButton(cursor, customer_id, document_id):
    """
        Generuje kod html przycisku "Opłać teraz" lub "Spłać saldo" z unikalnym linkiem do płatności
        :param klient_id: id klienta
        :param faktura: id dokumentu (faktury)
        :return: Kod html przycisku "Opłać teraz"/"Spłać saldo" w zalezności od wartości parametrów
    """
    button_text = 'Opłać teraz' if document_id else 'Spłać saldo'
    link = getCashLinkByDocument(cursor, document_id) if document_id else getBalanceLinkByCustomer(cursor, customer_id)

    return """
            <table align="center">
                <tr>
                    <td style="padding: 5px 5px 30px">
                        <div><!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                                         href="{0}" style="height:45px;v-text-anchor:middle;width:155px;" arcsize="15%"
                                         strokecolor="#ffffff" fillcolor="#000000">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:Helvetica, Arial, sans-serif;font-size:14px;font-weight:regular;">
                                    {1}
                                </center>
                            </v:roundrect>
                            <![endif]-->
                            <a href="{2}"
                               style="background-color:#000000;border-radius:5px;color:#ffffff;display:inline-block;font-family:'Cabin', Helvetica, Arial, sans-serif;font-size:14px;font-weight:regular;line-height:45px;text-align:center;text-decoration:none;width:155px;-webkit-text-size-adjust:none;mso-hide:all;">
                                    {3}
                            </a>
                        </div>
                    </td>
                </tr>
            </table>
        """.format(link, button_text, link, button_text)

def createButtons(cursor, customer_id, document_id):
    """
    Generuje kod dwóch przycisków "Opłać teraz" i "Opłać saldo"
    :param klient_id: id klienta
    :param faktura: id dokumentu (faktury)
    :return: Kod html przycisku "Opłać teraz" i przycisku "Opłać saldo"
    """

    balance_button = createSingleButton(cursor, customer_id, None)
    document_button = createSingleButton(cursor, None, document_id)

    return balance_button + '<p style="text-align: center">lub</p>' + document_button