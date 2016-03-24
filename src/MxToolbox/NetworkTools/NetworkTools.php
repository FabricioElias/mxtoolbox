<?php
namespace MxToolbox\NetworkTools;

use MxToolbox\Exceptions\MxToolboxLogicException;
use MxToolbox\Exceptions\MxToolboxRuntimeException;

//use MxToolbox\Exceptions\MxToolboxRuntimeException;

/**
 * Class NetworkTools
 * @package MxToolbox\NetworkTools
 */
class NetworkTools extends DigParser
{

    /** @var array DNS resolvers IP addresses */
    private $dnsResolvers;
    /** @var string Path where is dig */
    private $digPath;
    /** @var string PTR record from the method checkExistPTR() */
    private $recordPTR;
    /** @var string domain name from the method checkExistPTR() */
    private $domainName;

    /**
     * Push one IP address of a DNS resolver to the resolvers list
     * (tcp port 53 must be open)
     * (UDP sockets will sometimes appear to have opened without an error, even if the remote host is unreachable.)
     * (DNS Works On Both TCP and UDP ports)
     * @param string $addr
     * @return $this
     * @throws MxToolboxLogicException
     */
    public function setDnsResolverIP($addr)
    {
        if ($this->validateIPAddress($addr) && $fss = @fsockopen('tcp://' . $addr, 53, $errno, $errstr, 5)) {
            fclose($fss);
            $this->dnsResolvers[] = $addr;
            return $this;
        }
        throw new MxToolboxLogicException('DNS Resolver: ' . $addr . ' do not response on port 53.');
    }

    /**
     * Set path to dig utility, etc: '/usr/bin/dig'
     * @param string $digPath
     * @return $this
     * @throws MxToolboxLogicException
     */
    public function setDigPath($digPath)
    {
        if (!empty($digPath) && is_file($digPath)) {
            $this->digPath = $digPath;
            return $this;
        }
        throw new MxToolboxLogicException('DIG file does not exist!');
    }

    /**
     * Set 'blResponse' in testResult array on true if is dnsbl hostname alive
     * @param array $testResults
     * @return $this
     */
    public function setDnsblResponse(&$testResults)
    {
        foreach ($testResults as $key => $val) {
            if ($this->isDnsblResponse($val['blHostName']))
                $testResults[$key]['blResponse'] = true;
        }
        return $this;
    }

    /**
     * Get Dns resolvers array
     * @return array
     */
    public function &getDnsResolvers()
    {
        return $this->dnsResolvers;
    }

    /**
     * Get DIG path
     * @return string
     */
    public function &getDigPath()
    {
        return $this->digPath;
    }

    /**
     * Get random DNS IP address from array
     * @return mixed
     * @throws MxToolboxLogicException
     */
    private function getRandomDNSResolverIP()
    {
        if (!count($this->dnsResolvers) > 0)
            throw new MxToolboxLogicException('No DNS resolver here!');
        return $this->dnsResolvers[array_rand($this->dnsResolvers, 1)];
    }

    /**
     * Check DNSBL PTR Record
     * TODO: ipv6 support
     * @param string $addr
     * @param string $dnsResolver
     * @param string $blackList
     * @param string $record 'A,TXT,AAAA?', default 'A'
     * @return string
     */
    public function getDigResult($addr, $dnsResolver, $blackList, $record = 'A')
    {
        // dig @127.0.0.1 +nocmd 2.0.0.127.xbl.spamhaus.org A
        $checkResult = shell_exec($this->digPath . ' @' . $dnsResolver .
            ' +time=3 +tries=1 +nocmd ' . $this->reverseIP($addr) . '.' . $blackList . ' ' . $record);
        return $checkResult;
    }

    /**
     * Check one hostname for response on 127.0.0.2
     * @param string $host
     * @return bool
     */
    public function isDnsblResponse(&$host) {
        $digOutput = $this->getDigResult('127.0.0.2', $this->getRandomDNSResolverIP(), $host, 'A');
        if ($this->isNoError($digOutput))
            return true;
        return false;
    }

    /**
     * Check all (use only alive rBLS - fast check!)
     * @param string $addr
     * @param array $testResult
     * @return $this
     * @throws MxToolboxRuntimeException
     */
    public function checkAllDnsbl($addr, &$testResult)
    {
        if ($this->validateIPAddress($addr) && count($testResult) > 0) {
            foreach ($testResult as &$blackList) {
                $digOutput = $this->getDigResult($addr, $this->getRandomDNSResolverIP(), $blackList['blHostName'], 'TXT');
                if ($this->isNoError($digOutput)) {
                    $blackList['blPositive'] = true;
                    $blackList['blPositiveResult'] = $this->getPositiveUrlAddresses($digOutput);
                }
                $blackList['blQueryTime'] = $this->getQueryTime($digOutput);
            }
            unset($blackList);
            return $this;
        }
        throw new MxToolboxRuntimeException(sprintf('Array is empty for dig checks in: %s\%s.',get_class(),__FUNCTION__));
    }

    /**
     * Reverse IP address 192.168.1.254 -> 254.1.168.192
     * @param string $addr
     * @return string
     */
    protected function reverseIP($addr)
    {
        $revIpAddr = explode(".", $addr);
        return $revIpAddr[3] . '.' . $revIpAddr[2] . '.' . $revIpAddr[1] . '.' . $revIpAddr[0];
    }

    /**
     * Validate if string is valid IP address
     * @param string $addr
     * @return boolean
     */
    protected function validateIPAddress($addr)
    {
        if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            return true;
        throw new MxToolboxLogicException('IP address: ' . $addr . ' is not valid.');
    }

    // under this not work

    /**
     * Check string is domain like
     * @param string $hostName
     * @return bool
     */
    private function checkHostName($hostName)
    {
        $validHostnameRegex = "/^[a-zA-Z0-9.\-]{2,256}\.[a-z]{2,6}$/";
        if (preg_match($validHostnameRegex, trim($hostName)))
            return true;
        return false;
    }


}