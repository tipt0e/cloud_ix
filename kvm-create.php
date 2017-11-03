<?php

function kvm_domain_create($conn, $poolname, $domain, $img, $newip, $vcpus = NULL, $mem = NULL)
{
    /* do it */
    $pool = libvirt_storagepool_lookup_by_name($conn, $poolname);
    echo "Storage Pool is $pool\n";
    $template = libvirt_storagevolume_lookup_by_name($pool, $img);
    echo "Setting up VM $domain\n";
    $xmldovol = shell_exec("sed 's/xmldummiez/$domain/g' /etc/libvirt/qemu/vol-doo.xml > /etc/libvirt/qemu/$domain-vol.xml");
    $xmldodom = shell_exec("sed 's/xmldummiez/$domain/g' /etc/libvirt/qemu/kvm-doo.xml > /etc/libvirt/qemu/$domain.xml");
    echo "memory set is $mem\n";
    /* XXX need to figure out the underlying numa shit */
    $setmem = "";
    if ($mem) 
       $setmem = ($mem * 1024);
    else
       $setmem = (1024 * 1024);
    $xmldodom = shell_exec("perl -p -i -e 's/XxXxXxX/$setmem/g' /etc/libvirt/qemu/$domain.xml");
    $volxml = `cat /etc/libvirt/qemu/$domain-vol.xml`;
    $domxml = `cat /etc/libvirt/qemu/$domain.xml`;

    echo "Creating VM Volume /pool/$domain.qcow2\n";
    $xvm = libvirt_storagevolume_create_xml_from($pool, $volxml, $template);
    echo "Creating VM $domain\n";
    $creat = libvirt_domain_define_xml($conn, $domxml);
    sleep(1);
    $vmres = libvirt_domain_lookup_by_name($conn, $domain);
    sleep(1);
    if ($vcpus) {
        $chvcpus = libvirt_domain_change_vcpus($vmres, $vcpus, NULL);
    }
    sleep(1);
    $startit = libvirt_domain_create($vmres);

    echo "----- Some Info ------\n";
    $vol = libvirt_storagepool_list_volumes($pool);
    print_r($vol);
    $volinfo = libvirt_storagepool_get_info($pool);
    print_r($volinfo);
    sleep(30);
    echo "Configuring IP / HOSTNAME for VM $domain\n";

    /* template selection */
    switch ($img) {
    case "image-uno.qcow2":
        $oldip = 'doit';
	break;
    case "image-dos.qcow2":
        $oldip = '192.168.62.54';
        break;
    }
    $kvmip = $newip;

    $dnsconn = ssh2_connect('hydrogen', 22);
    ssh2_auth_pubkey_file($dnsconn, 'root', '/home/www/.ssh/id_rsa.pub', '/home/www/.ssh/id_rsa') or die("Could not AUTH");
    ssh2_exec($dnsconn, "sudo echo '$kvmip $domain.bytepimps.net $domain' >> /etc/hosts &");
    ssh2_exec($dnsconn, "systemctl restart dnsmasq");

    $sconn = ssh2_connect($oldip, 22);
    ssh2_auth_pubkey_file($sconn, 'root', '/home/www/.ssh/id_rsa.pub', '/home/www/.ssh/id_rsa') or die("Could not AUTH");
    ssh2_exec($sconn, "sudo su -c 'echo $domain > /etc/hostname'");
    ssh2_exec($sconn, "sudo echo '$kvmip      $domain.bytepimps.net $domain' >> /etc/hosts &");
    switch ($img) {
    case "centos-7-like-type":
        ssh2_exec($sconn, "sudo echo 'session    optional    pam_mkhomedir.so skel=/etc/skel umask=0077' >> /etc/pam.d/system-auth-ac");
        ssh2_exec($sconn, "sudo echo 'session    optional    pam_mkhomedir.so skel=/etc/skel umask=0077' >> /etc/pam.d/password-auth-ac");
        ssh2_exec($sconn, "sudo perl -p -i -e 's/$oldip/$kvmip/g' /etc/sysconfig/network-scripts/ifcfg-ens3");
        break;
    case "debian-buster-like-type.qcow2":
        ssh2_exec($sconn, "sudo perl -p -i -e 's/$oldip/$kvmip/g' /etc/network/interfaces");
        break;
    case "ubuntu-artful-like-type.qcow2":
        ssh2_exec($sconn, "sudo perl -p -i -e 's/$oldip/$kvmip/g' /etc/netplan/01-netcfg.yaml");
        break;
    }
    ssh2_exec($sconn, "sleep 2");
    
    $reboot = libvirt_domain_reboot($vmres, NULL);
}

function chefstrap($kvmip, $kvmname, $runlist)
{
/* XXX ssh2 is not working... yet...
    $xconn = ssh2_connect('your chef knife target', 22);
    ssh2_auth_pubkey_file($xconn, 'root', '/home/www/.ssh/id_rsa.pub', '/home/www/.ssh/id_rsa');
    ssh2_exec($xconn, "/usr/local/bin/knife bootstrap $kvmip -N $kvmname.bytepimps.net -r $runlist");
*/
    sleep(10);
    echo "Bootstrapping Chef on node $kvmname...\n\n"; 
    $sshit = shell_exec("ssh root@salty /usr/local/bin/knife bootstrap $kvmip -N $kvmname.bytepimps.net -r $runlist");
    echo "Done with Chef bootstrap.\n";
}

$lvconn = libvirt_connect('qemu+ssh://root@salty/system', false);
$lvpool = 'toilet';
$lvdomain = 'toilet-vm-swirl';
$lvimg = 'picc-a-booger-image.qcow2';
$lvnewip = '192.168.62.213';
$runit = 'recipe[fried-chiccen]';
/* if you want
$cpus = '2';
$memory = '2048';
*/

kvm_domain_create($lvconn, $lvpool, $lvdomain, $lvimg, $lvnewip);
/**
 * kvm_domain_create($lvconn, $lvpool, $lvdomain, $lvimg, $lvnewip, $cpus, $memory);
 */

sleep(44);
chefstrap($lvnewip, $lvdomain, $runit);
?>
