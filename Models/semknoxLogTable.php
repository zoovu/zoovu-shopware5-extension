<?php
namespace semknoxSearch\Models;
use Doctrine\ORM\Mapping as ORM;
/**
 * @ORM\Entity
 * @ORM\Table(name="semknox_logs")
 */
class semknoxLogTable
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    /**
     * @var integer
     * @ORM\Column(name="logtype", type="integer", nullable=false)
     */
    private $logtype;
    /**
     * @var integer
     * @ORM\Column(name="status", type="integer", nullable=false)
     */
    private $status = 0;
    /**
     * @var \DateTime
     * @ORM\Column(name="logtime", columnDefinition=" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP")
     */
    private $logtime = 0;
    /**
     * @var string
     * @ORM\Column(name="logtitle", type="string", nullable=false)
     */
    private $logtitle;
    /**
     * @var string
     * @ORM\Column(name="logdescr", type="text", nullable=false)
     */
    private $logdescr;
    /**
     * @param string $name
     */
    public function __construct()
    {
        $this->logtime = date("Y-m-d H:i:s");
    }
    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @return int
     */
    public function getLogType()
    {
        return $this->logtype;
    }
    /**
     * @param integer $l
     */
    public function setLogType($l)
    {
        $this->logtype= $l;
    }
    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }
    public function setStatus($l)
    {
        $this->status=$l;
    }
    public function getLogTime() {
    	return $this->logtime;
    }
    public function getLogTimei() {
    	return strtotime($this->logtime);
    }
    public function setLogTime($d='') {
    	if (trim($d)=='') { $d=date("Y-m-d H:i:s"); }
    	$this->logtime=$d;
    }
}
