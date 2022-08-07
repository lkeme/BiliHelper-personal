<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Util\Qrcode\Lib;

use Exception;

class QrRawCode
{
    public int $version;
    public ?array $datacode = [];
    public array $ecccode = [];
    public mixed $blocks;
    public array $rsblocks = []; //of RSblock
    public int $count;
    public int|float $dataLength;
    public int|float $eccLength;
    public mixed $b1;

    //----------------------------------------------------------------------
    public function __construct(QrInput $input)
    {
        $spec = array(0, 0, 0, 0, 0);

        $this->datacode = $input->getByteStream();
        if (is_null($this->datacode)) {
            throw new Exception('null input string');
        }

        QrSpec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

        $this->version = $input->getVersion();
        $this->b1 = QrSpec::rsBlockNum1($spec);
        $this->dataLength = QrSpec::rsDataLength($spec);
        $this->eccLength = QrSpec::rsEccLength($spec);
        $this->ecccode = array_fill(0, $this->eccLength, 0);
        $this->blocks = QrSpec::rsBlockNum($spec);

        $ret = $this->init($spec);
        if ($ret < 0) {
            throw new Exception('block alloc error');
            return null;
        }

        $this->count = 0;
    }

    //----------------------------------------------------------------------
    public function init(array $spec)
    {
        $dl = QrSpec::rsDataCodes1($spec);
        $el = QrSpec::rsEccCodes1($spec);
        $rs = QrRs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);


        $blockNo = 0;
        $dataPos = 0;
        $eccPos = 0;
        for ($i = 0; $i < QrSpec::rsBlockNum1($spec); $i++) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new QrRsBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        if (QrSpec::rsBlockNum2($spec) == 0)
            return 0;

        $dl = QrSpec::rsDataCodes2($spec);
        $el = QrSpec::rsEccCodes2($spec);
        $rs = QrRs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

        if ($rs == NULL) return -1;

        for ($i = 0; $i < QrSpec::rsBlockNum2($spec); $i++) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new QrRsBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        return 0;
    }

    //----------------------------------------------------------------------
    public function getCode(): int
    {
        $ret = null;

        if ($this->count < $this->dataLength) {
            $row = $this->count % $this->blocks;
            $col = $this->count / $this->blocks;
            if ($col >= $this->rsblocks[0]->dataLength) {
                $row += $this->b1;
            }
            $ret = (int)$this->rsblocks[(int)$row]->data[(int)$col];
        } else if ($this->count < $this->dataLength + $this->eccLength) {
            $row = ($this->count - $this->dataLength) % $this->blocks;
            $col = ($this->count - $this->dataLength) / $this->blocks;
            $ret = (int)$this->rsblocks[(int)$row]->ecc[(int)$col];
        } else {
            return 0;
        }
        $this->count++;

        return $ret;
    }
}
