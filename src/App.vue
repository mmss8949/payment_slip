<template>
  <v-app>
    <v-main>
      <!-- overlay -->
      <v-overlay :value="overlay">
        <v-row align="center">
          <v-progress-circular
            indeterminate
            size="45"
          ></v-progress-circular>
          <div class="text-h3 ml-7">
            繳費單產生中
          </div>
        </v-row>
      </v-overlay>
      <!-- alert -->
      <v-alert
        v-if="alert.show"
        :type="alert.type"
        text
      >{{alert.text}}
      </v-alert>

      <!-- snackbar -->
      <v-snackbar v-model="snackbarMsg.show">
        {{ snackbarMsg.text }}

        <template v-slot:action="{ attrs }">
          <v-btn
            color="pink"
            text
            v-bind="attrs"
            @click="snackbarMsg.show = false"
          >
            Close
          </v-btn>
        </template>
      </v-snackbar>
    </v-main>
  </v-app>
</template>

<script>
import VueBarcode from "vue-barcode";
var QRCode = require("qrcode");
import axios from "axios";

// pdf
var pdfMake = require("pdfmake/build/pdfmake.js");
var pdfFonts = require("pdfmake/build/vfs_fonts.js");
pdfMake.vfs = pdfFonts.pdfMake.vfs;

//test data
import multipleTestData from "@/assets/JSON/testData1.json";

export default {
  name: "App",

  components: {
    barcode: VueBarcode,
  },
  data: () => ({
    testMode: false,
    overlay: false,
    alert: {
      show: false,
      type: "info",
      text: "繳費單產生完畢",
    },
    snackbarMsg: {
      show: false,
      text: "",
    },
    img: {
      logoSquare: "",
      log: "",
    },
    testData: [],
    data: [],
  }),
  created() {
    const self = this;
    self.testMode =
      location.href.indexOf("localhost") != -1 ||
      location.href.indexOf("github") != -1;
    self.testData = multipleTestData;
  },
  mounted() {
    const self = this;
    self.main();
  },
  methods: {
    main: async function () {
      const self = this;
      try {
        let data;
        let msg = "";
        let content;
        let url;
        let fileName;
        let printResult;

        self.overlay = true;
        url = self.paresURL(location.href);
        if (url.success) {
          await Promise.all([
            self.toDataURL("paymentSlip/tools/img/dyu_logo_square.jpg"),
            self.toDataURL("paymentSlip/tools/img/dyu_logo.jpg"),
            self.getData({ data: url.value }),
          ]).then((values) => {
            self.img.logoSquare = values[0];
            self.img.logo = values[1];
            data = values[2];
          });

          fileName =
            data[0].payer["姓名"] && data[0].type_title
              ? data[0].payer["姓名"] + "_" + data[0].type_title
              : "繳費單";

          self.setPDFFont();
          data = self.processData(data);
          content = self.createPDF(data);
          printResult = await self.exportPDF(content, fileName);
          if (printResult.state)
            msg = "PDF檔案將自動下載，下載完畢請關閉本視窗。";
          else throw printResult.msg;
        } else {
          msg = "繳費單序號有錯誤";
        }
        self.overlay = false;
        self.alert.text = msg;
        self.alert.type = "info";
        self.alert.show = true;
        self.snackbarMsg.text = msg;
        self.snackbarMsg.show = true;
      } catch (e) {
        let msg = "繳費單產生出現錯誤";
        self.alert.text = msg;
        self.alert.type = "error";
        self.alert.show = true;
        self.overlay = false;
        self.snackbarMsg.text = msg;
        self.snackbarMsg.show = true;
        console.log(e.msg);
      }
    },
    paresURL: function (parseUrl) {
      let result = {};
      let url = new URL(parseUrl);
      let paramExist = url.searchParams.has("data");
      if (paramExist) {
        result.success = true;
        result.value = url.searchParams.get("data");
      } else result.success = false;

      return result;
    },
    getData: function (data) {
      return new Promise((resolve, reject) => {
        const self = this;
        if (self.testMode) resolve(self.testData.data);
        else {
          axios
            .post("./paymentSlip/tools/api/getPayment.php", data)
            .then((value) => {
              if (value.data.result == 1) resolve(value.data.data);
              else reject(value.data.msg);
            });
        }
      });
    },
    toDataURL: function (src) {
      return new Promise((resolve, reject) => {
        var img = new Image();
        img.crossOrigin = "Anonymous";
        img.onload = function () {
          var canvas = document.createElement("CANVAS");
          var ctx = canvas.getContext("2d");
          var dataURL;
          canvas.height = this.naturalHeight;
          canvas.width = this.naturalWidth;
          ctx.drawImage(this, 0, 0);
          dataURL = canvas.toDataURL("image/png");
          resolve(dataURL);
        };
        img.src = src;
        if (img.complete || img.complete === undefined) {
          img.src =
            "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";
          img.src = src;
        }
      });
    },
    textToBase64Barcode: function (text, options) {
      try {
        var canvas = document.createElement("canvas");
        JsBarcode(canvas, text, options);
        return canvas.toDataURL("image/png");
      } catch (e) {
        throw e;
      }
    },
    textToBase64QRcode: function (text, options) {
      var canvas = document.createElement("canvas");
      QRCode.toCanvas(canvas, text, function (error) {
        if (error) console.error(error);
      });
      return canvas.toDataURL("image/png");
    },
    setPDFFont: function () {
      let path = location.pathname;
      let fontPath =
        location.origin +
        path.replace(path.split("/").pop(), "") +
        "paymentSlip/tools/font";

      pdfMake.fontsFamily = {
        Roboto: {
          normal:
            "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Regular.ttf",
          bold: "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Medium.ttf",
          italics:
            "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-Italic.ttf",
          bolditalics:
            "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.66/fonts/Roboto/Roboto-MediumItalic.ttf",
        },
        NotoSansTC: {
          normal: fontPath + "/Noto_Sans_TC/NotoSansTC-Light.otf",
          bold: fontPath + "/Noto_Sans_TC/NotoSansTC-Bold.otf",
          italics: fontPath + "/Noto_Sans_TC/NotoSansTC-Black.otf",
          bolditalics: fontPath + "/Noto_Sans_TC/NotoSansTC-Black.otf",
        },
        Kaiu: {
          normal: fontPath + "/Kaiu/kaiu.ttf",
          bold: fontPath + "/Kaiu/kaiu.ttf",
          italics: fontPath + "/Kaiu/kaiu.ttf",
          bolditalics: fontPath + "/Kaiu/kaiu.ttf",
        },
      };
    },
    processData: function (data) {
      try {
        const self = this;
        data.forEach((element) => {
          let hiddenOptions = {
            format: "CODE39",
            displayValue: false,
          };
          let showOptions = {
            format: "CODE39",
            displayValue: true,
            fontOptions: "bold",
          };
          //sum fee
          let sum = parseInt(element.amount) + parseInt(element.post_fee);
          //taiwan pay
          element.twpayQRcode =
            element.qrcode_twpay && element.qrcode_twpay.length != 0
              ? self.textToBase64QRcode(element.qrcode_twpay, {})
              : null;

          element.vaccountBarcode =
            element.vaccount && element.vaccount.length != 0
              ? self.textToBase64Barcode(element.vaccount, {
                  ...showOptions,
                  text: `*${element.vaccount}*`,
                })
              : null;
          element.amountBarcode =
            element.amount && element.amount.length != 0
              ? self.textToBase64Barcode(element.amount, {
                  ...showOptions,
                  text: `*${element.amount}*`,
                })
              : null;
          element.con1Barcode =
            element.barcode_con1 && element.barcode_con1.length != 0
              ? self.textToBase64Barcode(element.barcode_con1, {
                  ...showOptions,
                  text: `*${element.barcode_con1}*`,
                })
              : null;
          element.con2Barcode =
            element.barcode_con2 && element.barcode_con2.length != 0
              ? self.textToBase64Barcode(element.barcode_con2, {
                  ...showOptions,
                  text: `*${element.barcode_con2}*`,
                })
              : null;
          element.con3Barcode =
            element.barcode_con3 && element.barcode_con3.length != 0
              ? self.textToBase64Barcode(element.barcode_con3, {
                  ...showOptions,
                  text: `*${element.barcode_con3}*`,
                })
              : null;
          element.postNoBarcode =
            element.post_no && element.post_no.length != 0
              ? self.textToBase64Barcode(element.post_no, {
                  ...showOptions,
                  text: `*${element.post_no}*`,
                })
              : null;
          element.vaccountPostBarcode =
            element.vaccount_post && element.vaccount_post.length != 0
              ? self.textToBase64Barcode(element.vaccount_post, {
                  ...showOptions,
                  text: `*${element.vaccount_post}*`,
                })
              : null;
          element.sumBarcode =
            sum && sum.length != 0
              ? self.textToBase64Barcode(sum, {
                  ...showOptions,
                  text: `*${sum}*`,
                })
              : null;
        });

        return data;
      } catch (e) {
        console.log(e);
        throw e;
      }
    },
    createPDF: function (data) {
      /* Note
       * absolutePosition
       ** x-axis 1.4 cm : 40
       ** y-axis 2.25 cm : 58
       * barcode
       * height 1 cm : 35
       */
      const self = this;
      let content = [];
      let pageBreak = {
        text: "",
        fontSize: 14,
        bold: true,
        pageBreak: "after",
        margin: [0, 0, 0, 8],
      };
      //loop
      data.forEach((item, index) => {
        content.push(
          getHeader(item),
          getPaymentDetail(item),
          getPaymentDetailTable(item),
          index != data.length - 1 ? pageBreak : {}
        );
      });

      return content;

      //function
      function getHeader({ sender_address, payer_address, payer_name }) {
        //check header mode
        let letter_state =
          sender_address &&
          payer_address &&
          sender_address.length != 0 &&
          payer_address.length != 0;
        let content = [];

        if (letter_state) {
          //data
          let logo;
          let address;
          let letter;

          //layout
          let logoLayout;
          let addressLayout;
          let headerLayout;

          //data
          logo = {
            text: self.img.logoSquare,
            width: 30,
          };
          address = {
            row1: {
              col1: { alignment: "left", text: "大葉大學", fontSize: 10 },
            },
            row2: {
              col1: {
                col1: {
                  text: "地址：彰化縣大村鄉學府路168號",
                  alignment: "left",
                  fontSize: 10,
                },
                col2: {
                  text: "電話：(04)851-1888",
                  alignment: "left",
                  fontSize: 10,
                },
              },
            },
          };
          letter = {
            row1: {
              col1: {
                text: payer_address,
                fontSize: 15,
                alignment: "center",
              },
            },
            row2: {
              col1: {
                text: payer_name + "           收",
                fontSize: 20,
                alignment: "center",
              },
            },
          };

          logoLayout = {
            image: logo.text,
            width: logo.width,
          };
          addressLayout = {
            table: {
              body: [
                //row1
                [
                  //col1
                  {
                    text: address.row1.col1.text,
                    alignment: address.row1.col1.alignment,
                    fontSize: address.row1.col1.fontSize,
                  },
                ],
                //row2
                [
                  //col1
                  {
                    columns: [
                      //col1
                      {
                        text: address.row2.col1.col1.text,
                        alignment: address.row2.col1.col1.alignment,
                        fontSize: address.row2.col1.col1.fontSize,
                      },
                      //col2
                      {
                        alignment: address.row2.col1.col2.alignment,
                        text: address.row2.col1.col2.text,
                        fontSize: address.row2.col1.col2.fontSize,
                        margin: [75, 0, 0, 0],
                      },
                    ],
                  },
                ],
              ],
            },
            layout: "noBorders",
            margin: [5, 0, 0, 0],
          };
          headerLayout = {
            columns: [logoLayout, addressLayout],
            margin: [0, 0, 0, 50],
          };

          letter = {
            table: {
              body: [
                [
                  {
                    text: letter.row1.col1.text,
                    fontSize: letter.row1.col1.fontSize,
                    alignment: letter.row1.col1.alignment,
                    margin: [50, 0, 0, 0],
                  },
                ],
                [
                  {
                    text: letter.row2.col1.text,
                    fontSize: letter.row2.col1.fontSize,
                    alignment: letter.row2.col1.alignment,
                    margin: [50, 25, 0, 0],
                  },
                ],
              ],
            },
            layout: "noBorders",
            margin: [0, 0, 0, 50],
          };

          content.push(headerLayout, letter);
        } else {
          //Logo
          let logo = {
            text: self.img.logo,
          };
          //layout
          let logoLayout = {
            image: logo.text,
            width: 250,
          };
          content.push(logoLayout);
        }
        return content;
      }
      function getPaymentDetail({
        type_title,
        payment_id,
        payer,
        personal_remark,
        payment_items,
        amount_loan,
        amount,
        remark_1,
        remark_2,
        remark_3,
      }) {
        let content = [];

        //data
        let pageTitle;
        let paymentCode;
        let personalInfoTitle;
        let tableTitle;
        let paymentDetailTable;
        let pamentList;
        let remark1;
        let remark2;
        let remark3;

        //layout
        let pageTitleLayout;
        let paymentCodeLayout;
        let personalInfoTitleLayout;
        let tableTitleLayout;
        let paymentDetailTableLayout;
        let remark1Layout;
        let remark2Layout;
        let remark3Layout;

        //data setting
        pageTitle = {
          text: type_title,
          fontSize: 12,
          bold: false,
          alignment: "center",
        };
        paymentCode = {
          text: `NO: ${payment_id}`,
          alignment: "right",
        };
        personalInfoTitle = {
          payer: payer,
          payerFontSize: 12,
          personalRemark: personal_remark,
          personalRemarkFontSize: 12,
          alignment: "center",
        };
        tableTitle = {
          text: "應繳費用明細如下：",
          alignment: "left",
          fontSize: 10,
        };
        pamentList = getPaymentList(payment_items, 10, 10, "right");
        paymentDetailTable = {
          size: {
            widths: [200, 100, "*"],
          },
          row1: {
            col1: { text: "繳費項目", alignment: "center" },
            col2: { text: "金額", alignment: "center" },
            col3: { text: "繳款人資料", alignment: "center" },
          },
          row2: {
            col1: pamentList.titleList,
            col2: pamentList.amountList,
            col3: createPayrtInfo(
              personalInfoTitle.payer,
              personalInfoTitle.payerFontSize,
              personalInfoTitle.personalRemark,
              personalInfoTitle.personalRemarkFontSize
            ),
          },
          row3: {
            col1: {
              col1: {
                text: amount_loan != 0 ? `可貸金額為 ${amount_loan}` : "",
                fontSize: 10,
                alignment: "left",
              },
              col2: {
                text: "                    合計",
                fontSize: 10,
                alignment: "right",
              },
            },
            col2: {
              text: amount,
              fontSize: 10,
              alignment: "right",
            },
            col3: {
              text: "",
            },
          },
        };
        remark1 = {
          text: remark_1,
          fontSize: 10,
          bold: true,
        };
        remark2 = {
          text: remark_2,
          fontSize: 10,
          bold: true,
        };
        remark3 = {
          text: remark_3,
          fontSize: 10,
          bold: true,
        };

        //layout planning
        pageTitleLayout = {
          text: pageTitle.text,
          fontSize: pageTitle.fontSize,
          bold: pageTitle.bold,
          alignment: pageTitle.alignment,
          margin: [0, 30, 0, 0],
        };
        paymentCodeLayout = {
          text: paymentCode.text,
          alignment: paymentCode.alignment,
          margin: [0, -12, 0, 0],
        };
        personalInfoTitleLayout = {
          text: createPayrtInfo(
            personalInfoTitle.payer,
            personalInfoTitle.payerFontSize,
            personalInfoTitle.personalRemark,
            personalInfoTitle.personalRemarkFontSize
          ),
          alignment: personalInfoTitle.alignment,
          margin: [0, 5, 0, 0],
        };
        tableTitleLayout = {
          text: tableTitle.text,
          alignment: tableTitle.alignment,
          fontSize: tableTitle.fontSize,
          margin: [0, 5, 0, 5],
        };
        paymentDetailTableLayout = {
          table: {
            body: [
              //row1
              [
                //col1
                {
                  text: paymentDetailTable.row1.col1.text,
                  alignment: paymentDetailTable.row1.col1.alignment,
                },
                //col2
                {
                  text: paymentDetailTable.row1.col2.text,
                  alignment: paymentDetailTable.row1.col2.alignment,
                },
                //col3
                {
                  text: paymentDetailTable.row1.col3.text,
                  alignment: paymentDetailTable.row1.col3.alignment,
                },
              ],
              //row2
              [
                //col1
                {
                  stack: paymentDetailTable.row2.col1,
                },
                //col2
                {
                  stack: paymentDetailTable.row2.col2,
                },
                //col3
                {
                  stack: paymentDetailTable.row2.col3,
                  rowSpan: 2,
                },
              ],
              //row3
              [
                {
                  text: [
                    {
                      text: paymentDetailTable.row3.col1.col1.text,
                      fontSize: paymentDetailTable.row3.col1.col1.fontSize,
                      alignment: paymentDetailTable.row3.col1.col1.alignment,
                    },
                    {
                      text: paymentDetailTable.row3.col1.col2.text,
                      fontSize: paymentDetailTable.row3.col1.col2.fontSize,
                      alignment: paymentDetailTable.row3.col1.col2.alignment,
                    },
                  ],
                },
                {
                  text: paymentDetailTable.row3.col2.text,
                  fontSize: paymentDetailTable.row3.col2.fontSize,
                  alignment: paymentDetailTable.row3.col2.alignment,
                },
                { text: paymentDetailTable.row3.col3.text },
              ],
            ],
            widths: paymentDetailTable.size.widths,
          },
        };
        remark1Layout = {
          text: remark1.text,
          fontSize: remark1.fontSize,
          bold: remark1.bold,
        };
        remark2Layout = {
          text: remark2.text,
          fontSize: remark2.fontSize,
          bold: remark2.bold,
        };
        remark3Layout = {
          text: remark3.text,
          fontSize: remark3.fontSize,
          bold: remark3.bold,
        };

        content.push(
          pageTitleLayout,
          paymentCodeLayout,
          personalInfoTitleLayout,
          tableTitleLayout,
          paymentDetailTableLayout,
          remark1,
          remark2,
          remark3
        );

        //function
        function getPaymentList(
          payment_items,
          titleFontSize,
          amountFontSize,
          amountAlignment
        ) {
          let titleList = [];
          let amountList = [];
          let keys = Object.keys(payment_items);

          for (var i = 0; i < keys.length; i++) {
            let currentItem = keys[i];
            titleList.push({
              text: currentItem,
              fontSize: titleFontSize,
            });
            amountList.push({
              text: payment_items[currentItem],
              fontSize: amountFontSize,
              alignment: amountAlignment,
            });
          }

          return { titleList: titleList, amountList: amountList };
        }

        return content;
      }
      function getPaymentDetailTable({
        due_date,
        payer,
        payer_name,
        account_name,
        bank_name,
        vaccount,
        vaccountBarcode,
        amount,
        amountBarcode,
        stat_onlyATM,
        stat_free,
        bank_title,
        con1Barcode,
        con2Barcode,
        con3Barcode,
        postNoBarcode,
        vaccountPostBarcode,
        sumBarcode,
        twpayQRcode,
        personal_remark,
        post_fee,
      }) {
        /* 
        1.分隔線 
        2.逾期繳費
        3.繳費單title
        4.繳費單table
        */

        // condition
        const ATMState = stat_onlyATM == 1;
        const freeState = stat_free == 1;

        //data
        let alert;
        let paymentTitle;
        let paymentTable;

        //5 part layout
        let lineLayout = {};
        let alertLayout = {};
        let paymentTitleLayout = [];
        let paymentTableLayout = [];
        let freePayLayout = [];

        //start position
        let startPos = {
          x: 40,
          y: 535,
        };

        //data
        alert = {
          text: "逾期繳費  請勿代收",
          fontSize: 6,
        };

        paymentTitle = {
          bigTitle: {
            text: "繳款單",
            fontSize: 10,
          },
          smallTitle: {
            text: " (代收行收執聯)",
            fontSize: 8,
          },
          dataTitle: {
            text: "繳款截止日：" + due_date + "",
            fontSize: 8,
          },
        };

        paymentTable = {
          size: {
            widths: [8, 195, 80, 8, 179],
            heights: ["auto", "auto", 35, "auto"],
          },
          row1: {
            col1: { text: "\n\n\n全行代收專戶", fontSize: 10 },
            col2: { text: "ATM轉帳 / 跨行匯款：", fontSize: 11 },
            col3: {
              text: `姓名：${
                payer_name.length > 11 ? payer_name.slice(0, 11) : payer_name
              }`,
              alignment: "left",
              fontSize: 11,
            },
            col4: {
              text: "",
            },
            col5: {
              text: `應繳總金額：  ${amount}`,
              fontSize: 10,
            },
          },
          row2: {
            col1: { text: "" },
            col2: {
              tb1: {
                text: `戶名： ${account_name}
                銀行別： ${bank_name}
                帳號： ${vaccount}`,
                fontSize: 8,
              },
              tb2: {
                text: vaccountBarcode,
              },
              tb3: {
                text: `金額： ${amount}`,
                fontSize: 8,
              },
              tb4: {
                text: amountBarcode,
              },
            },
            col3: {
              text: `收  訖  章`,
              alignment: "center",
            },
            col4: {
              text: ATMState
                ? "\n\n\n收 款 人 資 訊 "
                : "\n\n\n便利商店專用條碼區",
              fontSize: 10,
              alignment: "center",
            },
            col5: ATMState
              ? {
                  col1: {
                    text: `${bank_title}以外的金融機構繳款者，請填寫匯款申請書。\n收款人資訊如下：`,
                    fontSize: 10,
                  },
                  col2: {
                    text: `收款人戶名： ${account_name}`,
                    fontSize: 11,
                  },
                  col3: {
                    text: `銀行名稱： ${bank_name}`,
                    fontSize: 11,
                  },
                  col4: {
                    text: `收款人帳號： ${vaccount}`,
                    fontSize: 11,
                  },
                  col5: {
                    text: `金額： ${amount}`,
                    fontSize: 11,
                  },
                  col6: {
                    text: "匯款手續費需自付。",
                    fontSize: 10,
                  },
                }
              : {
                  col1: {
                    text: con1Barcode,
                  },
                  col2: {
                    text: con2Barcode,
                  },
                  col3: {
                    text: con3Barcode,
                  },
                  col4: {
                    text: "(需另付手續費20元)",
                    fontSize: 8,
                  },
                },
          },
          row3: {
            col1: { text: "認證欄", alignment: "center", fontSize: 10 },
            col2: {
              tb1: {
                text: twpayQRcode,
              },
              tb2: {
                text: "台灣Pay\r\n(需另付手續費10元)",
                alignment: "left",
                fontSize: 10,
              },
            },
            col3: {
              text: "",
            },
            col4: { text: "" },
            col5: { text: "" },
          },
          row4: {
            col1: {
              text: freeState ? "匯款人資訊" : "郵政專用",
              alignment: "center",
              fontSize: 10,
            },
            col2: ATMState
              ? {
                  payer: payer,
                  payerFontSize: 10,
                  personalRemark: personal_remark,
                  personalRemarkFontSize: 10,
                }
              : {
                  row1: {
                    col1: {
                      text: " 收款專戶：",
                      fontSize: 7,
                    },
                    col2: {
                      text: " 帳單編號：",
                      fontSize: 7,
                    },
                    col3: {
                      text:
                        post_fee == 0
                          ? "應繳金額："
                          : "應繳金額：(內含手續費" + post_fee + "元)",
                      fontSize: 7,
                    },
                  },
                  row2: {
                    col1: {
                      text: bank_title,
                      fontSize: 7,
                    },
                    col2: {
                      text: "",
                    },
                    col3: {
                      text: "",
                    },
                  },
                  row3: {
                    col1: {
                      text: postNoBarcode,
                    },
                    col2: {
                      text: vaccountPostBarcode,
                    },
                    col3: {
                      text: sumBarcode,
                    },
                  },
                },
          },
        };

        //return final variable
        let paymentDetailTable = [];

        // 1. lineLayout
        lineLayout = {
          canvas: [
            { type: "line", x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 1 },
          ],
          absolutePosition: { x: startPos.x, y: startPos.y },
        };

        // 2. alertLayout
        alertLayout = {
          table: {
            body: [
              [
                {
                  text: alert.text,
                  fontSize: alert.fontSize,
                },
              ],
            ],
          },
          absolutePosition: { x: startPos.x + 370, y: startPos.y + 5 },
          // absolutePosition: { x: 410, y: 550 },
        };

        // 3. paymentTitleLayout
        paymentTitleLayout = [
          {
            text: [
              paymentTitle.bigTitle.text,
              {
                text: paymentTitle.smallTitle.text,
                fontSize: paymentTitle.smallTitle.fontSize,
              },
            ],
            fontSize: paymentTitle.bigTitle.fontSize,
            absolutePosition: { x: startPos.x + 110, y: startPos.y + 22 },
            // absolutePosition: { x: 150, y: 567 },
          },
          {
            text: paymentTitle.dataTitle.text,
            fontSize: paymentTitle.dataTitle.fontSize,
            absolutePosition: { x: startPos.x + 355, y: startPos.y + 22 },
            // absolutePosition: { x: 395, y: 567 },
          },
        ];

        // 4. paymentTableLayout
        paymentTableLayout = [
          {
            table: {
              body: [
                //row1
                [
                  //col1
                  {
                    text: paymentTable.row1.col1.text,
                    alignment: "center",
                    rowSpan: 2,
                    fontSize: paymentTable.row1.col1.fontSize,
                  },
                  //col2
                  {
                    text: paymentTable.row1.col2.text,
                    alignment: "left",
                    fontSize: paymentTable.row1.col2.fontSize,
                  },
                  //col3
                  {
                    text: paymentTable.row1.col3.text,
                    alignment: "left",
                    colSpan: 2,
                    maxHeight: 20,
                    fontSize: paymentTable.row1.col3.fontSize,
                  },
                  //col4
                  {
                    text: paymentTable.row1.col4.text,
                  },
                  //col5
                  {
                    text: paymentTable.row1.col5.text,
                    alignment: "left",
                    fontSize: paymentTable.row1.col5.fontSize,
                  },
                ],
                //row2
                [
                  //col1
                  {
                    text: paymentTable.row2.col1.text,
                  },
                  //col2
                  {
                    table: {
                      body: [
                        //tb1
                        [
                          {
                            text: paymentTable.row2.col2.tb1.text,
                            fontSize: paymentTable.row2.col2.tb1.fontSize,
                          },
                        ],
                        //tb2
                        [
                          {
                            image: paymentTable.row2.col2.tb2.text,
                            width: 165,
                            height: 32,
                          },
                        ],
                        //tb3
                        [
                          {
                            text: paymentTable.row2.col2.tb3.text,
                            fontSize: paymentTable.row2.col2.tb3.fontSize,
                          },
                        ],
                        //tb4
                        [
                          {
                            image: paymentTable.row2.col2.tb4.text,
                            width: 65,
                            height: 19,
                          },
                        ],
                      ],
                    },
                    layout: "noBorders",
                  },
                  //col3
                  {
                    text: paymentTable.row2.col3.text,
                    alignment: paymentTable.row2.col3.alignment,
                    margin: [0, 83, 0, 0],
                  },
                  //col4
                  {
                    text: paymentTable.row2.col4.text,
                    alignment: paymentTable.row2.col4.alignment,
                    rowSpan: 2,
                    fontSize: paymentTable.row2.col4.fontSize,
                  },
                  //col5
                  {
                    stack: ATMState
                      ? [
                          {
                            text: paymentTable.row2.col5.col1.text,
                            fontSize: paymentTable.row2.col5.col1.fontSize,
                          },
                          {
                            text: paymentTable.row2.col5.col2.text,
                            fontSize: paymentTable.row2.col5.col2.fontSize,
                          },
                          {
                            text: paymentTable.row2.col5.col3.text,
                            fontSize: paymentTable.row2.col5.col3.fontSize,
                          },
                          {
                            text: paymentTable.row2.col5.col4.text,
                            fontSize: paymentTable.row2.col5.col4.fontSize,
                          },
                          {
                            text: paymentTable.row2.col5.col5.text,
                            fontSize: paymentTable.row2.col5.col5.fontSize,
                          },
                          {
                            text: paymentTable.row2.col5.col6.text,
                            fontSize: paymentTable.row2.col5.col6.fontSize,
                          },
                        ]
                      : [
                          {
                            image: paymentTable.row2.col5.col1.text,
                            width: 105,
                            height: 32,
                            margin: [0, 10, 0, 10],
                          },
                          {
                            image: paymentTable.row2.col5.col2.text,
                            width: 175,
                            height: 32,
                            margin: [0, 0, 0, 10],
                          },
                          {
                            image: paymentTable.row2.col5.col3.text,
                            width: 175,
                            height: 32,
                          },
                          {
                            text: paymentTable.row2.col5.col4.text,
                            fontSize: paymentTable.row2.col5.col4.fontSize,
                          },
                        ],
                    rowSpan: 2,
                  },
                ],
                //row3
                [
                  //col1
                  {
                    text: paymentTable.row3.col1.text,
                    alignment: paymentTable.row3.col1.alignment,
                    fontSize: paymentTable.row3.col1.fontSize,
                  },
                  //col2
                  {
                    table: {
                      body: [
                        [
                          //tb1
                          {
                            image: paymentTable.row3.col2.tb1.text,
                            width: 35,
                            height: 35,
                            margin: [150, 0, 0, 0],
                          },
                          //tb2
                          {
                            text: paymentTable.row3.col2.tb2.text,
                            alignment: paymentTable.row3.col2.tb2.alignment,
                            fontSize: paymentTable.row3.col2.tb2.fontSize,
                            margin: [0, 15, 0, 0],
                          },
                        ],
                      ],
                    },
                    layout: "noBorders",
                    colSpan: 2,
                  },
                  //col3
                  {
                    text: paymentTable.row3.col3.text,
                  },
                  //col4
                  {
                    text: paymentTable.row3.col4.text,
                  },
                  //col5
                  {
                    text: paymentTable.row3.col5.text,
                  },
                ],
                //row4
                [
                  //col1
                  {
                    text: paymentTable.row4.col1.text,
                    alignment: paymentTable.row4.col1.alignment,
                    fontSize: paymentTable.row4.col1.fontSize,
                  },
                  //col2
                  {
                    table: {
                      body: ATMState
                        ? [
                            [
                              createPayrtInfo(
                                paymentTable.row4.col2.payer,
                                paymentTable.row4.col2.payerFontSize,
                                paymentTable.row4.col2.personalRemark,
                                paymentTable.row4.col2.personalRemarkFontSize
                              ),
                            ],
                          ]
                        : [
                            //row1
                            [
                              //col1
                              {
                                text: paymentTable.row4.col2.row1.col1.text,
                                fontSize:
                                  paymentTable.row4.col2.row1.col1.fontSize,
                              },
                              //col2
                              {
                                text: paymentTable.row4.col2.row1.col2.text,
                                fontSize:
                                  paymentTable.row4.col2.row1.col2.fontSize,
                              },
                              //col3
                              {
                                text: paymentTable.row4.col2.row1.col3.text,
                                fontSize:
                                  paymentTable.row4.col2.row1.col3.fontSize,
                              },
                            ],
                            //row2
                            [
                              //col1
                              {
                                text: paymentTable.row4.col2.row2.col1.text,
                                fontSize:
                                  paymentTable.row4.col2.row2.col1.fontSize,
                              },
                              //col2
                              {
                                text: paymentTable.row4.col2.row2.col2.text,
                              },
                              //col3
                              {
                                text: paymentTable.row4.col2.row2.col3.text,
                              },
                            ],
                            //row3
                            [
                              //col1
                              {
                                image: paymentTable.row4.col2.row3.col1.text,
                                width: 100,
                                height: 32,
                                margin: [0, 0, 40, 0],
                              },
                              //col2
                              {
                                image: paymentTable.row4.col2.row3.col2.text,
                                width: 165,
                                height: 32,
                                margin: [0, 0, 40, 0],
                              },
                              //col3
                              //加總後的barcode 條碼圖案 2021/06/16 by cash
                              {
                                image: paymentTable.row4.col2.row3.col3.text,
                                width: 70,
                                height: 32,
                              },
                            ],
                          ],
                    },
                    layout: "noBorders",
                    colSpan: 4,
                  },
                  //col3
                  {
                    text: "",
                  },
                  //col4
                  {
                    text: "",
                  },
                  //col5
                  {
                    text: "",
                  },
                ],
              ],
              widths: paymentTable.size.widths,
              heights: paymentTable.size.heights,
              // heights: ["auto", "auto", 52, "auto"],
            },
            absolutePosition: { x: startPos.x, y: startPos.y + 33 },
            // absolutePosition: { x: 40, y: 578 },
          },
        ];
        // 5. freePayLayout
        freePayLayout = [
          {
            text: "無須繳費",
            fontSize: 125,
            opacity: 0.6,
            absolutePosition: { x: startPos.x, y: startPos.y + 55 },
          },
        ];

        paymentTableLayout = paymentDetailTable.push(
          lineLayout,
          alertLayout,
          ...paymentTitleLayout,
          paymentTableLayout,
          freeState ? freePayLayout : []
        );

        return paymentDetailTable;
      }
      function createPayrtInfo(
        payer,
        payerFontSize,
        personalRemark,
        personalRemarkFontSize
      ) {
        let payerInfoAry = [];
        let payerInfoKey = Object.keys(payer);
        for (var i = 0; i < payerInfoKey.length; i++) {
          payerInfoAry.push({
            text: `${payerInfoKey[i]} : ${payer[payerInfoKey[i]]}   `,
            fontSize: payerFontSize,
          });
        }
        if (personalRemark != null && personalRemark != "null") {
          payerInfoAry.push({
            text: `${personalRemark}`,
            fontSize: personalRemarkFontSize,
          });
        }
        return payerInfoAry;
      }
    },
    exportPDF: function (content, fileName) {
      return new Promise((resolve, reject) => {
        try {
          var docDefinition = {
            content: content,
            // pageSize: "A4",
            // pageMargins: [40, 60, 40, 10],
            defaultStyle: {
              font: "Kaiu",
            },
          };
          function unhandled() {
            reject({ state: false, msg: "timeout" });
          }
          setTimeout(() => {
            reject(new Error("timeout"));
            window.removeEventListener("unhandledrejection", unhandled);
          }, 2000);

          window.addEventListener("unhandledrejection", unhandled);

          pdfMake
            .createPdf(docDefinition, null, pdfMake.fontsFamily)
            .download(fileName + ".pdf", () => {
              resolve({ state: true, msg: "sucess!" });
            });
        } catch (e) {
          reject({ state: false, msg: e });
        }
      });
    },
  },
};
</script>
